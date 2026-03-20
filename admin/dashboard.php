<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';

require_admin();

$pdo = db();

/* -------------------------------------------------
   Ensure listings.sort_order exists (best-effort)
--------------------------------------------------*/
function ensure_listings_sort_order(PDO $pdo): void {
  try {
    $pdo->query("SELECT sort_order FROM listings LIMIT 1");
  } catch (Throwable $e) {
    try {
      $pdo->exec("ALTER TABLE listings ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
      $pdo->exec("UPDATE listings SET sort_order = id WHERE sort_order = 0");
    } catch (Throwable $e2) {
      // ignore
    }
  }
}
ensure_listings_sort_order($pdo);

/* -------------------------------------------------
   Normalize sort_order for rows that are still 0
--------------------------------------------------*/
try {
  $pdo->exec("UPDATE listings SET sort_order = id WHERE sort_order = 0");
} catch (Throwable $e) {}

/* -------------------------------------------------
   Helper for pagination links
--------------------------------------------------*/
function admin_dashboard_url(array $changes = []): string {
  $query = $_GET;
  foreach ($changes as $k => $v) {
    if ($v === null) {
      unset($query[$k]);
    } else {
      $query[$k] = $v;
    }
  }
  $qs = http_build_query($query);
  return BASE_URL . '/admin/dashboard.php' . ($qs ? ('?' . $qs) : '');
}

/* -------------------------------------------------
   POST actions must run BEFORE header.php output
--------------------------------------------------*/

/* Move listing */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_id'], $_POST['move_dir'])) {
  $move_id = (int)$_POST['move_id'];
  $dir = trim((string)$_POST['move_dir']);

  if ($move_id > 0) {
    $st = $pdo->prepare("SELECT id, sort_order, status FROM listings WHERE id=?");
    $st->execute([$move_id]);
    $cur = $st->fetch(PDO::FETCH_ASSOC);

    if ($cur && ($cur['status'] ?? '') === 'approved') {
      $curOrder = (int)$cur['sort_order'];

      if ($curOrder === 0) {
        $pdo->prepare("UPDATE listings SET sort_order=id WHERE id=?")->execute([$move_id]);
        $curOrder = $move_id;
      }

      if ($dir === 'top') {
        $minOrder = (int)$pdo->query("SELECT MIN(sort_order) FROM listings WHERE status='approved'")->fetchColumn();
        $newOrder = $minOrder - 1;

        $upd = $pdo->prepare("UPDATE listings SET sort_order=? WHERE id=?");
        $upd->execute([$newOrder, $move_id]);

        header('Location: ' . admin_dashboard_url(['moved' => 1]));
        exit;
      }

      if ($dir === 'up') {
        $st2 = $pdo->prepare("
          SELECT id, sort_order
          FROM listings
          WHERE status='approved' AND sort_order < ?
          ORDER BY sort_order DESC, id DESC
          LIMIT 1
        ");
        $st2->execute([$curOrder]);
      } else {
        $st2 = $pdo->prepare("
          SELECT id, sort_order
          FROM listings
          WHERE status='approved' AND sort_order > ?
          ORDER BY sort_order ASC, id ASC
          LIMIT 1
        ");
        $st2->execute([$curOrder]);
      }

      $nbr = $st2->fetch(PDO::FETCH_ASSOC);

      if ($nbr) {
        $nbrId = (int)$nbr['id'];
        $nbrOrder = (int)$nbr['sort_order'];

        try {
          $pdo->beginTransaction();
          $pdo->prepare("UPDATE listings SET sort_order=? WHERE id=?")->execute([$nbrOrder, $move_id]);
          $pdo->prepare("UPDATE listings SET sort_order=? WHERE id=?")->execute([$curOrder, $nbrId]);
          $pdo->commit();
        } catch (Throwable $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
        }
      }
    }
  }

  header('Location: ' . admin_dashboard_url(['moved' => 1]));
  exit;
}

/* Delete listing */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $delete_id = (int)$_POST['delete_id'];

  if ($delete_id > 0) {
    try {
      $pdo->beginTransaction();

      $stmtImgs = $pdo->prepare("SELECT path FROM listing_images WHERE listing_id=?");
      $stmtImgs->execute([$delete_id]);
      $imgs = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);

      $stmtDelImgs = $pdo->prepare("DELETE FROM listing_images WHERE listing_id=?");
      $stmtDelImgs->execute([$delete_id]);

      $stmtDel = $pdo->prepare("DELETE FROM listings WHERE id=?");
      $stmtDel->execute([$delete_id]);

      $pdo->commit();

      foreach ($imgs as $im) {
        $rel = (string)$im['path'];
        $full = __DIR__ . '/../uploads/' . ltrim($rel, '/');
        if (is_file($full)) @unlink($full);
      }

      header('Location: ' . admin_dashboard_url(['deleted' => 1]));
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      header('Location: ' . admin_dashboard_url(['delete_error' => 1]));
      exit;
    }
  }

  header('Location: ' . admin_dashboard_url());
  exit;
}

/* -------------------------------------------------
   Pagination setup
--------------------------------------------------*/
$perPage = 30;

$pendingPage  = isset($_GET['pending_page']) ? max(1, (int)$_GET['pending_page']) : 1;
$approvedPage = isset($_GET['approved_page']) ? max(1, (int)$_GET['approved_page']) : 1;

$pendingOffset  = ($pendingPage - 1) * $perPage;
$approvedOffset = ($approvedPage - 1) * $perPage;

/* -------------------------------------------------
   Stats
--------------------------------------------------*/
$pending  = (int)$pdo->query("SELECT COUNT(*) FROM listings WHERE status='pending'")->fetchColumn();
$approved = (int)$pdo->query("SELECT COUNT(*) FROM listings WHERE status='approved'")->fetchColumn();
$rejected = (int)$pdo->query("SELECT COUNT(*) FROM listings WHERE status='rejected'")->fetchColumn();
$cats     = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

/* -------------------------------------------------
   Pending list
--------------------------------------------------*/
$pendingTotalPages = max(1, (int)ceil($pending / $perPage));

$pendingStmt = $pdo->prepare("
  SELECT
    l.id,
    l.title,
    COALESCE(l.title_en, '') AS title_en,
    l.city,
    COALESCE(l.city_en, '') AS city_en,
    COALESCE(l.source_lang, 'fa') AS source_lang,
    l.status,
    l.created_at,
    COALESCE(l.list_view_count, 0) AS list_view_count,
    COALESCE(l.detail_view_count, 0) AS detail_view_count,
    c.name AS cat_name
  FROM listings l
  LEFT JOIN categories c ON c.id = l.category_id
  WHERE l.status='pending'
  ORDER BY l.id DESC
  LIMIT ? OFFSET ?
");
$pendingStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$pendingStmt->bindValue(2, $pendingOffset, PDO::PARAM_INT);
$pendingStmt->execute();
$pendingList = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   Approved list
--------------------------------------------------*/
$approvedTotalPages = max(1, (int)ceil($approved / $perPage));

$approvedStmt = $pdo->prepare("
  SELECT
    id,
    title,
    COALESCE(title_en, '') AS title_en,
    status,
    created_at,
    sort_order,
    COALESCE(source_lang, 'fa') AS source_lang,
    COALESCE(list_view_count, 0) AS list_view_count,
    COALESCE(detail_view_count, 0) AS detail_view_count
  FROM listings
  WHERE status='approved'
  ORDER BY sort_order ASC, id DESC
  LIMIT ? OFFSET ?
");
$approvedStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$approvedStmt->bindValue(2, $approvedOffset, PDO::PARAM_INT);
$approvedStmt->execute();
$approvedList = $approvedStmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   Translation completeness helper
--------------------------------------------------*/
function admin_dashboard_text_ok($v): bool {
  return trim((string)$v) !== '';
}

function admin_dashboard_translation_label(array $row): array {
  $source = trim((string)($row['source_lang'] ?? 'fa'));
  if (!in_array($source, ['fa', 'en'], true)) $source = 'fa';

  $faOk = admin_dashboard_text_ok($row['title'] ?? '');
  $enOk = admin_dashboard_text_ok($row['title_en'] ?? '');

  if ($source === 'fa') {
    if ($faOk && $enOk) {
      return ['label' => 'دو‌زبانه کامل', 'class' => 'good'];
    }
    if ($faOk && !$enOk) {
      return ['label' => 'نیازمند ترجمه انگلیسی', 'class' => 'warn'];
    }
    return ['label' => 'متن فارسی ناقص', 'class' => 'bad'];
  }

  if ($enOk && $faOk) {
    return ['label' => 'دو‌زبانه کامل', 'class' => 'good'];
  }
  if ($enOk && !$faOk) {
    return ['label' => 'نیازمند ترجمه فارسی', 'class' => 'warn'];
  }
  return ['label' => 'متن انگلیسی ناقص', 'class' => 'bad'];
}

require_once __DIR__ . '/../inc/header.php';
?>

<style>
.admin-section-title { margin-top:24px; }
.admin-sub-note { color:#666; margin:4px 0 12px; }
.admin-pager {
  display:flex;
  gap:8px;
  flex-wrap:wrap;
  margin:12px 0 18px;
  align-items:center;
}
.admin-pager a,
.admin-pager span {
  padding:6px 10px;
  border:1px solid #ddd;
  border-radius:8px;
  text-decoration:none;
}
.admin-pager .current {
  background:#f3f3f3;
  font-weight:bold;
}
.admin-status-badge {
  display:inline-block;
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
}
.admin-status-badge.good { background:#e8f7e8; }
.admin-status-badge.warn { background:#fff4d6; }
.admin-status-badge.bad  { background:#fde8e8; }
.admin-mini {
  font-size:12px;
  color:#666;
  line-height:1.7;
}
</style>

<h1 class="h1">Admin Dashboard</h1>

<?php if (!empty($_GET['deleted'])): ?>
  <div class="success">آگهی با موفقیت حذف شد.</div>
<?php endif; ?>

<?php if (!empty($_GET['moved'])): ?>
  <div class="success">ترتیب نمایش آگهی‌های تاییدشده ذخیره شد.</div>
<?php endif; ?>

<?php if (!empty($_GET['delete_error'])): ?>
  <div class="error">حذف آگهی انجام نشد.</div>
<?php endif; ?>

<div class="stats">
  <div class="stat">Pending: <strong><?php echo $pending; ?></strong></div>
  <div class="stat">Approved: <strong><?php echo $approved; ?></strong></div>
  <div class="stat">Rejected: <strong><?php echo $rejected; ?></strong></div>
  <div class="stat">Categories: <strong><?php echo $cats; ?></strong></div>
</div>

<div class="adminlinks">
  <a class="btn" href="<?php echo BASE_URL; ?>/admin/pending_listings.php">Pending Review</a>
  <a class="btn" href="<?php echo BASE_URL; ?>/admin/categories.php">Manage Categories</a>
  <a class="btn" href="<?php echo BASE_URL; ?>/admin/listings.php">All Listings</a>
  <a class="btn" href="<?php echo BASE_URL; ?>/admin/users.php">Users</a>
  <a class="btn" href="<?php echo BASE_URL; ?>/admin/ads.php">Side Ads</a>
  <a class="btn" href="<?php echo BASE_URL; ?>/admin/site_settings.php">Site Settings</a>
  <a class="btn" href="<?php echo BASE_URL; ?>/admin/logout.php">Logout</a>
</div>

<hr style="margin:20px 0">

<h2 class="h1 admin-section-title" style="font-size:18px;">آگهی‌های در انتظار تایید</h2>
<p class="admin-sub-note">ابتدا pending ها نمایش داده می‌شوند تا بتوانید قبل از تایید، ترجمه و عکس‌ها را بررسی و اصلاح کنید.</p>

<table class="table">
  <tr>
    <th>ID</th>
    <th>عنوان</th>
    <th>وضعیت ترجمه</th>
    <th>زبان مبدا</th>
    <th>تاریخ</th>
    <th>نمایش لیست</th>
    <th>ورود به آگهی</th>
    <th>عملیات</th>
  </tr>

  <?php foreach($pendingList as $it): ?>
    <?php $tr = admin_dashboard_translation_label($it); ?>
    <tr>
      <td><?php echo (int)$it['id']; ?></td>
      <td>
        <div><?php echo h($it['title']); ?></div>
        <?php if (!empty($it['title_en'])): ?>
          <div class="admin-mini" dir="ltr" style="unicode-bidi:plaintext;"><?php echo h($it['title_en']); ?></div>
        <?php endif; ?>
        <div class="admin-mini">
          <?php echo h($it['cat_name'] ?? ''); ?>
          <?php if (!empty($it['city'])): ?>
            <span class="dot">•</span><?php echo h($it['city']); ?>
          <?php endif; ?>
        </div>
      </td>
      <td>
        <span class="admin-status-badge <?php echo h($tr['class']); ?>">
          <?php echo h($tr['label']); ?>
        </span>
      </td>
      <td><?php echo h($it['source_lang']); ?></td>
      <td><?php echo h($it['created_at']); ?></td>
      <td><?php echo (int)$it['list_view_count']; ?></td>
      <td><?php echo (int)$it['detail_view_count']; ?></td>
      <td style="white-space:nowrap;">
        <a class="btn" href="<?php echo BASE_URL; ?>/admin/edit_listing.php?id=<?php echo (int)$it['id']; ?>">Edit / Translate</a>

        <form method="post" style="display:inline-block; margin:0;"
              onsubmit="return confirm('آیا مطمئن هستید که این آگهی حذف شود؟ این عمل قابل بازگشت نیست.');">
          <input type="hidden" name="delete_id" value="<?php echo (int)$it['id']; ?>">
          <button class="btn" type="submit">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>

  <?php if(!count($pendingList)): ?>
    <tr><td colspan="8">آگهی pending وجود ندارد.</td></tr>
  <?php endif; ?>
</table>

<?php if ($pendingTotalPages > 1): ?>
  <div class="admin-pager">
    <?php if ($pendingPage > 1): ?>
      <a href="<?php echo h(admin_dashboard_url(['pending_page' => $pendingPage - 1])); ?>">صفحه قبل pending</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $pendingTotalPages; $i++): ?>
      <?php if ($i === $pendingPage): ?>
        <span class="current"><?php echo $i; ?></span>
      <?php else: ?>
        <a href="<?php echo h(admin_dashboard_url(['pending_page' => $i])); ?>"><?php echo $i; ?></a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if ($pendingPage < $pendingTotalPages): ?>
      <a href="<?php echo h(admin_dashboard_url(['pending_page' => $pendingPage + 1])); ?>">صفحه بعد pending</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<hr style="margin:28px 0">

<h2 class="h1 admin-section-title" style="font-size:18px;">آگهی‌های تاییدشده و ترتیب نمایش</h2>
<p class="admin-sub-note">در این بخش فقط approved ها نمایش داده می‌شوند. با ⤒ آگهی را به اولین ردیف ببر، یا با ▲▼ جابه‌جا کن.</p>

<table class="table">
  <tr>
    <th>ID</th>
    <th>عنوان</th>
    <th>وضعیت</th>
    <th>تاریخ</th>
    <th>Order</th>
    <th>نمایش لیست</th>
    <th>ورود به آگهی</th>
    <th>عملیات</th>
  </tr>

  <?php foreach($approvedList as $it): ?>
  <tr>
    <td><?php echo (int)$it['id']; ?></td>
    <td>
      <div><?php echo h($it['title']); ?></div>
      <?php if (!empty($it['title_en'])): ?>
        <div class="admin-mini" dir="ltr" style="unicode-bidi:plaintext;"><?php echo h($it['title_en']); ?></div>
      <?php endif; ?>
    </td>
    <td><?php echo h($it['status']); ?></td>
    <td><?php echo h($it['created_at']); ?></td>
    <td><?php echo (int)$it['sort_order']; ?></td>
    <td><?php echo (int)$it['list_view_count']; ?></td>
    <td><?php echo (int)$it['detail_view_count']; ?></td>

    <td style="white-space:nowrap;">

      <form method="post" style="display:inline-block; margin:0;">
        <input type="hidden" name="move_id" value="<?php echo (int)$it['id']; ?>">
        <input type="hidden" name="move_dir" value="top">
        <button class="btn" type="submit" title="برو به اولین ردیف">⤒</button>
      </form>

      <form method="post" style="display:inline-block; margin:0;">
        <input type="hidden" name="move_id" value="<?php echo (int)$it['id']; ?>">
        <input type="hidden" name="move_dir" value="up">
        <button class="btn" type="submit">▲</button>
      </form>

      <form method="post" style="display:inline-block; margin:0;">
        <input type="hidden" name="move_id" value="<?php echo (int)$it['id']; ?>">
        <input type="hidden" name="move_dir" value="down">
        <button class="btn" type="submit">▼</button>
      </form>

      <a class="btn" href="<?php echo BASE_URL; ?>/admin/edit_listing.php?id=<?php echo (int)$it['id']; ?>">Edit</a>

      <form method="post" style="display:inline-block; margin:0;"
            onsubmit="return confirm('آیا مطمئن هستید که این آگهی حذف شود؟ این عمل قابل بازگشت نیست.');">
        <input type="hidden" name="delete_id" value="<?php echo (int)$it['id']; ?>">
        <button class="btn" type="submit">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>

  <?php if(!count($approvedList)): ?>
    <tr><td colspan="8">آگهی تاییدشده‌ای وجود ندارد.</td></tr>
  <?php endif; ?>
</table>

<?php if ($approvedTotalPages > 1): ?>
  <div class="admin-pager">
    <?php if ($approvedPage > 1): ?>
      <a href="<?php echo h(admin_dashboard_url(['approved_page' => $approvedPage - 1])); ?>">صفحه قبل approved</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $approvedTotalPages; $i++): ?>
      <?php if ($i === $approvedPage): ?>
        <span class="current"><?php echo $i; ?></span>
      <?php else: ?>
        <a href="<?php echo h(admin_dashboard_url(['approved_page' => $i])); ?>"><?php echo $i; ?></a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if ($approvedPage < $approvedTotalPages): ?>
      <a href="<?php echo h(admin_dashboard_url(['approved_page' => $approvedPage + 1])); ?>">صفحه بعد approved</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>