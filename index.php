<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

$site_name = t('site_name');

if (is_en()) {
    $page_title = $site_name . ' | Post and Search Listings in Multiple Categories';
    $page_desc  = 'Browse the latest listings and classifieds in multiple categories.';
} else {
    $page_title = $site_name . ' | ثبت و جستجوی آگهی در دسته‌بندی‌های مختلف';
    $page_desc  = 'مشاهده جدیدترین آگهی‌ها و نیازمندی‌ها در دسته‌بندی‌های مختلف.';
}

try {
    $pdo = db();
    $stmt = $pdo->query("SELECT name, name_en FROM categories ORDER BY id DESC LIMIT 5");
    $catsSeo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($catsSeo)) {
        $catNames = array();
        foreach ($catsSeo as $c) {
            $catNames[] = localized_field($c, 'name');
        }

        if (is_en()) {
            $page_desc = 'Browse the latest listings in categories such as ' . implode(', ', $catNames) . ' on ' . $site_name . '.';
        } else {
            $page_desc = 'مشاهده جدیدترین آگهی‌ها در دسته‌بندی‌های مختلف مانند ' . implode('، ', $catNames) . ' در ' . $site_name . '.';
        }
    }
} catch (Throwable $e) {
    // keep default description
}

if (!isset($pdo) || !$pdo) {
    $pdo = db();
}

/* -------------------------
   Safe filters (backward-compatible)
--------------------------*/
$q        = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$city     = isset($_GET['city']) ? trim((string)$_GET['city']) : '';
$page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}

$perPage = 30;
$offset  = ($page - 1) * $perPage;

$meta_robots = '';
if ($q !== '' || $category !== '' || $city !== '') {
    $meta_robots = 'noindex,follow';
}

if ($q !== '') {
    if (is_en()) {
        $page_title = 'Search "' . $q . '" | ' . $site_name;
        $page_desc  = 'Search results for "' . $q . '" on ' . $site_name;
    } else {
        $page_title = 'جستجوی "' . $q . '" | ' . $site_name;
        $page_desc  = 'نتایج جستجو برای "' . $q . '" در ' . $site_name;
    }
} elseif ($category !== '' || $city !== '') {
    if (is_en()) {
        $page_title = 'Filtered Listings | ' . $site_name;
        $page_desc  = 'Filtered listings on ' . $site_name;
    } else {
        $page_title = 'آگهی‌های فیلترشده | ' . $site_name;
        $page_desc  = 'آگهی‌های فیلترشده در ' . $site_name;
    }
}

/* برای صفحه اول بدون فیلتر canonical صفحه اصلی بماند */
if ($q === '' && $category === '' && $city === '' && $page === 1) {
    $current_url = append_lang_to_url(base_site_url() . '/');
}

require_once __DIR__ . '/inc/header.php';

/* ---------- تشخیص وجود sort_order ---------- */
$has_sort = false;
try {
    $pdo->query("SELECT sort_order FROM listings LIMIT 1");
    $has_sort = true;
} catch (Throwable $e) {
    $has_sort = false;
}

/* -------------------------
   WHERE مشترک برای count و list
--------------------------*/
$whereSql = " FROM listings l
              JOIN categories c ON c.id = l.category_id
              WHERE l.status = 'approved' ";

$params = array();

if ($q !== '') {
    $whereSql .= " AND (
        l.title LIKE ? OR l.body LIKE ? OR
        COALESCE(l.title_en,'') LIKE ? OR COALESCE(l.body_en,'') LIKE ?
    ) ";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($category !== '') {
    $whereSql .= " AND c.slug = ? ";
    $params[] = $category;
}

if ($city !== '') {
    $whereSql .= " AND (
        COALESCE(l.city,'') LIKE ? OR
        COALESCE(l.city_en,'') LIKE ?
    ) ";
    $params[] = '%' . $city . '%';
    $params[] = '%' . $city . '%';
}

/* -------------------------
   COUNT query
--------------------------*/
$countSql = "SELECT COUNT(*) AS cnt " . $whereSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();

$totalPages = (int)ceil($totalItems / $perPage);
if ($totalPages < 1) {
    $totalPages = 1;
}

/* -------------------------
   Main listings query
   SAFE: same default order as current site
--------------------------*/
$listSql = "SELECT l.*, c.name AS cat_name, c.name_en AS cat_name_en, c.slug AS cat_slug " . $whereSql;

if ($has_sort) {
    $listSql .= " ORDER BY l.sort_order ASC, l.id DESC ";
} else {
    $listSql .= " ORDER BY l.id DESC ";
}

$listSql .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$list = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$shownIds = array();
foreach ($list as $row) {
    $shownIds[] = (int)$row['id'];
}
record_listing_impressions($shownIds);

/* برای select دسته‌ها */
$allCats = categories_all();

/* helper for pagination links */
function home_page_url_with_params($targetPage, $q, $category, $city) {
    $qs = array();

    if ($q !== '') {
        $qs['q'] = $q;
    }
    if ($category !== '') {
        $qs['category'] = $category;
    }
    if ($city !== '') {
        $qs['city'] = $city;
    }
    if ((int)$targetPage > 1) {
        $qs['page'] = (int)$targetPage;
    }

    $url = base_site_url() . '/';
    if (!empty($qs)) {
        $url .= '?' . http_build_query($qs);
    }

    return append_lang_to_url($url);
}
?>

<h1 class="h1"><?php echo h(is_en() ? 'Classifieds' : 'نیازمندی‌ها'); ?></h1>
<p class="muted"><?php echo h(is_en() ? 'Listings are shown after admin approval.' : 'آگهی‌ها بعد از تایید ادمین نمایش داده می‌شوند.'); ?></p>

<form method="get" action="<?php echo h(BASE_URL); ?>/" style="margin:14px 0 18px 0; display:grid; gap:10px;">
  <input type="hidden" name="lang" value="<?php echo h(current_lang()); ?>">

  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px;">
    <select name="category">
      <option value=""><?php echo h(is_en() ? 'All categories' : 'همه دسته‌ها'); ?></option>
      <?php foreach ($allCats as $c): ?>
        <option value="<?php echo h($c['slug']); ?>" <?php echo ($category === $c['slug'] ? 'selected' : ''); ?>>
          <?php echo h(localized_field($c, 'name')); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input
      type="text"
      name="city"
      value="<?php echo h($city); ?>"
      placeholder="<?php echo h(is_en() ? 'City' : 'شهر'); ?>"
    >
  </div>

  <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
    <button type="submit"><?php echo h(is_en() ? 'Apply Filters' : 'اعمال فیلتر'); ?></button>
    <a href="<?php echo h(append_lang_to_url(BASE_URL . '/')); ?>" style="text-decoration:none;">
      <?php echo h(is_en() ? 'Reset' : 'حذف فیلترها'); ?>
    </a>

    <?php if ($q !== '' || $category !== '' || $city !== ''): ?>
      <span class="muted">
        <?php echo h(is_en() ? 'Filters are active' : 'فیلتر فعال است'); ?>
      </span>
    <?php endif; ?>
  </div>
</form>

<div class="muted" style="margin-bottom:12px;">
  <?php
    if (is_en()) {
        echo h('Total results: ' . $totalItems);
    } else {
        echo h('تعداد نتایج: ' . $totalItems);
    }
  ?>
</div>

<div class="listings">
  <?php foreach($list as $it): ?>
    <?php
      $imgs = listing_images_lang($it['id'], current_lang());
      $thumb = count($imgs) ? (UPLOAD_URL . $imgs[0]['path']) : (BASE_URL . '/assets/noimg.png');

      $titleText = localized_field($it, 'title');
      $catText   = localized_field($it, 'cat_name', 'cat_name_en');
      $cityText  = localized_field($it, 'city');
    ?>
    <a class="card" href="<?php echo h(listing_url($it['id'])); ?>">
      <div class="thumb">
        <img src="<?php echo h($thumb); ?>" alt="<?php echo h($titleText); ?>" loading="lazy" decoding="async">
      </div>
      <div class="meta">
        <div class="title"><?php echo h($titleText); ?></div>
        <div class="sub">
          <span class="tag"><?php echo h($catText); ?></span>
          <?php if (!empty($cityText)): ?><span class="dot">•</span><span><?php echo h($cityText); ?></span><?php endif; ?>
          <?php if (!empty($it['approved_at'])): ?><span class="dot">•</span><span><?php echo h($it['approved_at']); ?></span><?php endif; ?>
        </div>
      </div>
    </a>
  <?php endforeach; ?>

  <?php if (!count($list)): ?>
    <div class="empty"><?php echo h(t('nothing_found')); ?></div>
  <?php endif; ?>
</div>

<?php if ($totalItems > $perPage): ?>
  <?php
    $startPage = max(1, $page - 2);
    $endPage   = min($totalPages, $page + 2);

    if (($endPage - $startPage) < 4) {
        if ($startPage === 1) {
            $endPage = min($totalPages, $startPage + 4);
        } elseif ($endPage === $totalPages) {
            $startPage = max(1, $endPage - 4);
        }
    }
  ?>
  <div style="margin-top:18px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
    <?php if ($page > 1): ?>
      <a href="<?php echo h(home_page_url_with_params($page - 1, $q, $category, $city)); ?>" style="padding:8px 12px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
        <?php echo h(is_en() ? 'Previous' : 'قبلی'); ?>
      </a>
    <?php endif; ?>

    <?php if ($startPage > 1): ?>
      <a href="<?php echo h(home_page_url_with_params(1, $q, $category, $city)); ?>" style="padding:8px 12px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">1</a>
      <?php if ($startPage > 2): ?>
        <span class="muted">...</span>
      <?php endif; ?>
    <?php endif; ?>

    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
      <?php if ($p === $page): ?>
        <span style="padding:8px 12px; border:1px solid #111; border-radius:10px; background:#111; color:#fff;"><?php echo h($p); ?></span>
      <?php else: ?>
        <a href="<?php echo h(home_page_url_with_params($p, $q, $category, $city)); ?>" style="padding:8px 12px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
          <?php echo h($p); ?>
        </a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if ($endPage < $totalPages): ?>
      <?php if ($endPage < ($totalPages - 1)): ?>
        <span class="muted">...</span>
      <?php endif; ?>
      <a href="<?php echo h(home_page_url_with_params($totalPages, $q, $category, $city)); ?>" style="padding:8px 12px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
        <?php echo h($totalPages); ?>
      </a>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
      <a href="<?php echo h(home_page_url_with_params($page + 1, $q, $category, $city)); ?>" style="padding:8px 12px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
        <?php echo h(is_en() ? 'Next' : 'بعدی'); ?>
      </a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/inc/footer.php'; ?>