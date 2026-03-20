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

/* Keep canonical URL as homepage for first page without filters */
if ($q === '' && $category === '' && $city === '' && $page === 1) {
    $current_url = append_lang_to_url(base_site_url() . '/');
}

require_once __DIR__ . '/inc/header.php';

/* ---------- Detect existence of sort_order column ---------- */
$has_sort = false;
try {
    $pdo->query("SELECT sort_order FROM listings LIMIT 1");
    $has_sort = true;
} catch (Throwable $e) {
    $has_sort = false;
}

/* -------------------------
   Shared WHERE clause for count and list queries
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
   SAFE: same default ordering as current site
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

/* Load categories for select dropdown */
$allCats = categories_all();

/* Helper function for pagination links */
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