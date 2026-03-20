<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_admin();
require_once __DIR__ . '/../inc/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = db();

function admin_has_column(PDO $pdo, string $table, string $col): bool {
  try {
    $rows = $pdo->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      if (($r['name'] ?? '') === $col) return true;
    }
  } catch (Throwable $e) {
    // ignore
  }
  return false;
}

$has_title_en        = admin_has_column($pdo, 'listings', 'title_en');
$has_body_en         = admin_has_column($pdo, 'listings', 'body_en');
$has_city_en         = admin_has_column($pdo, 'listings', 'city_en');
$has_source_lang     = admin_has_column($pdo, 'listings', 'source_lang');
$has_img_lang_scope  = admin_has_column($pdo, 'listing_images', 'lang_scope');
$has_img_sort_order  = admin_has_column($pdo, 'listing_images', 'sort_order');

$selectCols = "*, COALESCE(list_view_count, 0) AS list_view_count, COALESCE(detail_view_count, 0) AS detail_view_count";
if ($has_title_en)    $selectCols .= ", COALESCE(title_en, '') AS title_en";
if ($has_body_en)     $selectCols .= ", COALESCE(body_en, '') AS body_en";
if ($has_city_en)     $selectCols .= ", COALESCE(city_en, '') AS city_en";
if ($has_source_lang) $selectCols .= ", COALESCE(source_lang, 'fa') AS source_lang";

$stmt = $pdo->prepare("
  SELECT $selectCols
  FROM listings
  WHERE id=?
");
$stmt->execute([$id]);
$it = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$it) {
  echo '<div class="empty">آگهی پیدا نشد.</div>';
  require_once __DIR__ . '/../inc/footer.php';
  exit;
}

$msg = '';
$errors = [];

function normalize_img_scope($scope): string {
  $scope = trim((string)$scope);
  if (!in_array($scope, ['both', 'fa', 'en'], true)) {
    return 'both';
  }
  return $scope;
}

/* ---------------- Delete image handler ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_img_id'])) {
  $img_id = (int)$_POST['delete_img_id'];

  $st = $pdo->prepare("SELECT id, path FROM listing_images WHERE id=? AND listing_id=?");
  $st->execute([$img_id, $id]);
  $img = $st->fetch(PDO::FETCH_ASSOC);

  if ($img) {
    try {
      $pdo->beginTransaction();

      $del = $pdo->prepare("DELETE FROM listing_images WHERE id=?");
      $del->execute([$img_id]);

      $pdo->commit();

      $rel = (string)$img['path'];
      $full = __DIR__ . '/../uploads/' . ltrim($rel, '/');
      if (is_file($full)) {
        @unlink($full);
      }

      $msg = 'تصویر حذف شد.';
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'حذف تصویر انجام نشد.';
    }
  } else {
    $errors[] = 'تصویر معتبر نیست.';
  }

  $stmt->execute([$id]);
  $it = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ---------------- Update listing + image scopes + add images ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_img_id'])) {

  $action = trim($_POST['action'] ?? 'save');

  // فارسی
  $title_fa = trim($_POST['title'] ?? '');
  $body_fa  = trim($_POST['body'] ?? '');
  $city_fa  = trim($_POST['city'] ?? '');

  // انگلیسی
  $title_en = trim($_POST['title_en'] ?? '');
  $body_en  = trim($_POST['body_en'] ?? '');
  $city_en  = trim($_POST['city_en'] ?? '');

  $status = trim($_POST['status'] ?? $it['status']);
  if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $status = 'pending';
  }

  if ($action === 'approve') {
    $status = 'approved';
  }

  $source_lang = trim($_POST['source_lang'] ?? ($it['source_lang'] ?? 'fa'));
  if (!in_array($source_lang, ['fa', 'en'], true)) {
    $source_lang = 'fa';
  }

  // اعتبارسنجی متن مبنا
  if ($source_lang === 'fa') {
    if ($title_fa === '' || mb_strlen($title_fa, 'UTF-8') > MAX_TITLE_LEN) {
      $errors[] = 'عنوان فارسی معتبر نیست.';
    }
    if ($body_fa === '' || mb_strlen($body_fa, 'UTF-8') > MAX_BODY_LEN) {
      $errors[] = 'متن فارسی معتبر نیست.';
    }
  } else {
    if ($title_en === '' || mb_strlen($title_en, 'UTF-8') > MAX_TITLE_LEN) {
      $errors[] = 'عنوان انگلیسی معتبر نیست.';
    }
    if ($body_en === '' || mb_strlen($body_en, 'UTF-8') > MAX_BODY_LEN) {
      $errors[] = 'متن انگلیسی معتبر نیست.';
    }
  }

  // fallback امن برای نسخه مقابل
  if ($title_fa === '' && $title_en !== '') $title_fa = $it['title'] ?? '';
  if ($body_fa === ''  && $body_en !== '')  $body_fa  = $it['body'] ?? '';
  if ($city_fa === ''  && $city_en !== '')  $city_fa  = $it['city'] ?? '';

  if ($title_en === '' && $title_fa !== '') $title_en = $it['title_en'] ?? '';
  if ($body_en === ''  && $body_fa !== '')  $body_en  = $it['body_en'] ?? '';
  if ($city_en === ''  && $city_fa !== '')  $city_en  = $it['city_en'] ?? '';

  // scope تصاویر فعلی
  $imgScopeMap = [];
  if (!empty($_POST['img_scope']) && is_array($_POST['img_scope'])) {
    foreach ($_POST['img_scope'] as $imgId => $scope) {
      $imgId = (int)$imgId;
      if ($imgId > 0) {
        $imgScopeMap[$imgId] = normalize_img_scope($scope);
      }
    }
  }

  // scope تصاویر جدید
  $img1_scope = normalize_img_scope($_POST['img1_scope'] ?? 'both');
  $img2_scope = normalize_img_scope($_POST['img2_scope'] ?? 'both');

  // فایل‌های جدید
  $newFiles = [];
  if (!empty($_FILES['img1']) && ($_FILES['img1']['name'] ?? '') !== '') {
    $newFiles[] = ['file' => $_FILES['img1'], 'scope' => $img1_scope];
  }
  if (!empty($_FILES['img2']) && ($_FILES['img2']['name'] ?? '') !== '') {
    $newFiles[] = ['file' => $_FILES['img2'], 'scope' => $img2_scope];
  }

  $currentImgs = listing_images($id);
  $currentCount = is_array($currentImgs) ? count($currentImgs) : 0;

  if ($currentCount + count($newFiles) > 2) {
    $errors[] = 'حداکثر ۲ تصویر برای هر آگهی مجاز است. ابتدا یک تصویر را حذف کنید و سپس تصویر جدید اضافه کنید.';
  }

  foreach ($newFiles as $nf) {
    $msgv = validate_image_upload($nf['file']);
    if ($msgv) $errors[] = $msgv;
  }

  if (!count($errors)) {
    try {
      $pdo->beginTransaction();

      // آپدیت متن‌های آگهی
      $fields = [
        'title = ?',
        'body = ?',
        'city = ?',
        'status = ?'
      ];

      $values = [
        $title_fa,
        $body_fa,
        $city_fa,
        $status
      ];

      if ($has_title_en) {
        $fields[] = 'title_en = ?';
        $values[] = $title_en;
      }
      if ($has_body_en) {
        $fields[] = 'body_en = ?';
        $values[] = $body_en;
      }
      if ($has_city_en) {
        $fields[] = 'city_en = ?';
        $values[] = $city_en;
      }
      if ($has_source_lang) {
        $fields[] = 'source_lang = ?';
        $values[] = $source_lang;
      }

      $values[] = $id;

      $sql = "UPDATE listings SET " . implode(', ', $fields) . " WHERE id=?";
      $upd = $pdo->prepare($sql);
      $upd->execute($values);

      // ذخیره scope تصاویر فعلی
      if ($has_img_lang_scope && !empty($imgScopeMap)) {
        $updImgScope = $pdo->prepare("
          UPDATE listing_images
          SET lang_scope=?
          WHERE id=? AND listing_id=?
        ");

        foreach ($imgScopeMap as $imgId => $scope) {
          $updImgScope->execute([$scope, $imgId, $id]);
        }
      }

      // افزودن تصاویر جدید
      foreach ($newFiles as $nf) {
        $saved = save_uploaded_image($nf['file']);
        if ($saved) {
          if ($has_img_lang_scope && $has_img_sort_order) {
            $ins = $pdo->prepare("
              INSERT INTO listing_images(listing_id, path, lang_scope, sort_order, created_at)
              VALUES (?, ?, ?, ?, ?)
            ");
            $ins->execute([$id, $saved, $nf['scope'], time(), now()]);
          } elseif ($has_img_lang_scope) {
            $ins = $pdo->prepare("
              INSERT INTO listing_images(listing_id, path, lang_scope, created_at)
              VALUES (?, ?, ?, ?)
            ");
            $ins->execute([$id, $saved, $nf['scope'], now()]);
          } else {
            $ins = $pdo->prepare("
              INSERT INTO listing_images(listing_id, path, created_at)
              VALUES (?, ?, ?)
            ");
            $ins->execute([$id, $saved, now()]);
          }
        }
      }

      $pdo->commit();

      $msg = ($action === 'approve') ? 'ذخیره شد و آگهی تایید شد.' : 'ذخیره شد.';

      $stmt->execute([$id]);
      $it = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'ذخیره تغییرات انجام نشد.';
    }
  }
}

// تصاویر فعلی برای نمایش
$imgs = listing_images($id);

// نرمال‌سازی scope تصاویر برای نمایش
if (is_array($imgs)) {
  foreach ($imgs as &$imx) {
    $imx['lang_scope'] = $has_img_lang_scope
      ? normalize_img_scope($imx['lang_scope'] ?? 'both')
      : 'both';
  }
  unset($imx);
}

$title_en_val    = $has_title_en ? ($it['title_en'] ?? '') : '';
$body_en_val     = $has_body_en ? ($it['body_en'] ?? '') : '';
$city_en_val     = $has_city_en ? ($it['city_en'] ?? '') : '';
$source_lang_val = $has_source_lang ? ($it['source_lang'] ?? 'fa') : 'fa';
?>

<h1 class="h1">ویرایش آگهی #<?php echo (int)$it['id']; ?></h1>

<div class="subline">
  <span>نمایش در لیست: <?php echo (int)$it['list_view_count']; ?></span>
  <span class="dot">•</span>
  <span>ورود به آگهی: <?php echo (int)$it['detail_view_count']; ?></span>
  <span class="dot">•</span>
  <span>وضعیت فعلی: <?php echo h($it['status']); ?></span>
  <?php if ($has_source_lang): ?>
    <span class="dot">•</span>
    <span>زبان مبدا: <?php echo h($source_lang_val); ?></span>
  <?php endif; ?>
</div>

<?php if ($msg): ?>
  <div class="success"><?php echo h($msg); ?></div>
<?php endif; ?>

<?php if (count($errors)): ?>
  <div class="error">
    <?php foreach($errors as $e): ?>
      <div><?php echo h($e); ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2 class="h1" style="font-size:16px;">تصاویر فعلی</h2>

<?php if (is_array($imgs) && count($imgs)): ?>
  <div class="gallery">
    <?php foreach($imgs as $im): ?>
      <div style="display:inline-block; text-align:center; vertical-align:top; margin:0 14px 14px 0;">
        <img src="<?php echo h(UPLOAD_URL . $im['path']); ?>" alt="image" style="max-width:240px; display:block; margin-bottom:8px;">

        <?php if ($has_img_lang_scope): ?>
          <label style="display:block; margin-bottom:6px;">نمایش این تصویر در:</label>
          <select name="img_scope[<?php echo (int)$im['id']; ?>]" form="listing-edit-form" style="margin-bottom:8px;">
            <option value="both" <?php echo (($im['lang_scope'] ?? 'both') === 'both') ? 'selected' : ''; ?>>هر دو زبان</option>
            <option value="fa" <?php echo (($im['lang_scope'] ?? '') === 'fa') ? 'selected' : ''; ?>>فقط فارسی</option>
            <option value="en" <?php echo (($im['lang_scope'] ?? '') === 'en') ? 'selected' : ''; ?>>فقط انگلیسی</option>
          </select>
        <?php else: ?>
          <div class="muted" style="margin-bottom:8px;">حالت نمایش: هر دو زبان</div>
        <?php endif; ?>

        <form method="post" style="margin-top:8px;" onsubmit="return confirm('این تصویر حذف شود؟');">
          <input type="hidden" name="delete_img_id" value="<?php echo (int)$im['id']; ?>">
          <button class="btn" type="submit">حذف تصویر</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="empty">تصویری برای این آگهی ثبت نشده است.</div>
<?php endif; ?>

<hr style="margin:18px 0;">

<form id="listing-edit-form" class="form" method="post" enctype="multipart/form-data">

  <?php if ($has_source_lang): ?>
    <label>زبان مبدا آگهی</label>
    <select name="source_lang">
      <option value="fa" <?php echo ($source_lang_val === 'fa') ? 'selected' : ''; ?>>فارسی</option>
      <option value="en" <?php echo ($source_lang_val === 'en') ? 'selected' : ''; ?>>English</option>
    </select>
  <?php endif; ?>

  <h2 class="h1" style="font-size:16px; margin-top:18px;">متن فارسی</h2>

  <label>عنوان فارسی</label>
  <input
    type="text"
    name="title"
    value="<?php echo h($it['title'] ?? ''); ?>"
    maxlength="<?php echo (int)MAX_TITLE_LEN; ?>"
  >

  <label>شهر فارسی</label>
  <input
    type="text"
    name="city"
    value="<?php echo h($it['city'] ?? ''); ?>"
  >

  <label>متن فارسی آگهی</label>
  <textarea
    name="body"
    rows="8"
    maxlength="<?php echo (int)MAX_BODY_LEN; ?>"
  ><?php echo h($it['body'] ?? ''); ?></textarea>

  <?php if ($has_title_en || $has_body_en || $has_city_en): ?>
    <h2 class="h1" style="font-size:16px; margin-top:22px;">English Content</h2>

    <?php if ($has_title_en): ?>
      <label>English Title</label>
      <input
        type="text"
        name="title_en"
        value="<?php echo h($title_en_val); ?>"
        maxlength="<?php echo (int)MAX_TITLE_LEN; ?>"
      >
    <?php endif; ?>

    <?php if ($has_city_en): ?>
      <label>English City</label>
      <input
        type="text"
        name="city_en"
        value="<?php echo h($city_en_val); ?>"
      >
    <?php endif; ?>

    <?php if ($has_body_en): ?>
      <label>English Description</label>
      <textarea
        name="body_en"
        rows="8"
        maxlength="<?php echo (int)MAX_BODY_LEN; ?>"
      ><?php echo h($body_en_val); ?></textarea>
    <?php endif; ?>
  <?php endif; ?>

  <h2 class="h1" style="font-size:16px; margin-top:22px;">وضعیت و انتشار</h2>

  <label>وضعیت</label>
  <select name="status">
    <option value="pending"  <?php echo (($it['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>pending</option>
    <option value="approved" <?php echo (($it['status'] ?? '') === 'approved') ? 'selected' : ''; ?>>approved</option>
    <option value="rejected" <?php echo (($it['status'] ?? '') === 'rejected') ? 'selected' : ''; ?>>rejected</option>
  </select>

  <h2 class="h1" style="font-size:16px; margin-top:22px;">افزودن / جایگزینی تصویر</h2>

  <div class="muted" style="margin-bottom:10px;">
    حداکثر ۲ تصویر مجاز است. برای جایگزینی، ابتدا یکی از تصاویر فعلی را حذف کنید و سپس تصویر جدید آپلود کنید.
  </div>

  <label>افزودن تصویر جدید (اختیاری)</label>
  <input type="file" name="img1" accept="image/*">

  <?php if ($has_img_lang_scope): ?>
    <label>نمایش تصویر جدید ۱ در:</label>
    <select name="img1_scope">
      <option value="both">هر دو زبان</option>
      <option value="fa">فقط فارسی</option>
      <option value="en">فقط انگلیسی</option>
    </select>
  <?php endif; ?>

  <label>افزودن تصویر دوم (اختیاری)</label>
  <input type="file" name="img2" accept="image/*">

  <?php if ($has_img_lang_scope): ?>
    <label>نمایش تصویر جدید ۲ در:</label>
    <select name="img2_scope">
      <option value="both">هر دو زبان</option>
      <option value="fa">فقط فارسی</option>
      <option value="en">فقط انگلیسی</option>
    </select>
  <?php endif; ?>

  <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
    <button class="btn" type="submit" name="action" value="save">ذخیره</button>
    <button class="btn" type="submit" name="action" value="approve" onclick="return confirm('آگهی ذخیره و تایید شود؟');">ذخیره و تایید</button>
  </div>
</form>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>