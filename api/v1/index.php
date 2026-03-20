<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/api_helpers.php';

$method = api_request_method();
$lang   = api_lang();

if ($method !== 'GET') {
    api_error('Method not allowed', 405);
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim(BASE_URL, '/');
$prefix = $base . '/api/v1';

$path = $uri;

// حذف BASE_URL از اول مسیر
if ($base !== '' && strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}

$path = '/' . ltrim($path, '/');

// انتظار داریم مسیر از /api/v1 شروع شود
if (strpos($path, '/api/v1') !== 0) {
    api_error('Invalid API path', 404);
}

$route = substr($path, strlen('/api/v1'));
$route = trim($route, '/');

$pdo = db();

/*
|--------------------------------------------------------------------------
| GET /api/v1/categories
|--------------------------------------------------------------------------
*/
if ($route === 'categories') {
    $rows = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

    $out = array();
    foreach ($rows as $row) {
        $out[] = api_category_transform($row, $lang);
    }

    api_success($out);
}

/*
|--------------------------------------------------------------------------
| GET /api/v1/listings
| Optional: ?q=...&category=services&limit=20
|--------------------------------------------------------------------------
*/
/*
if ($route === 'listings') {
    $q = trim($_GET['q'] ?? '');
    $categorySlug = trim($_GET['category'] ?? '');
    $limit = (int)($_GET['limit'] ?? 20);

    if ($limit < 1) $limit = 20;
    if ($limit > 100) $limit = 100;

    $sql = "
        SELECT l.*, c.name AS cat_name, c.name_en AS cat_name_en, c.slug AS cat_slug
        FROM listings l
        JOIN categories c ON c.id = l.category_id
        WHERE l.status = 'approved'
    ";

    $params = array();

    if ($categorySlug !== '') {
        $sql .= " AND c.slug = ? ";
        $params[] = $categorySlug;
    }

    if ($q !== '') {
        $sql .= " AND (
            l.title LIKE ? OR l.body LIKE ? OR
            COALESCE(l.title_en, '') LIKE ? OR COALESCE(l.body_en, '') LIKE ?
        ) ";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    $sql .= " ORDER BY l.id DESC LIMIT " . (int)$limit;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $out = array();
    foreach ($rows as $row) {
        $item = api_listing_transform($row, $lang);
        $item['category'] = array(
            'slug' => $row['cat_slug'] ?? '',
            'name' => function_exists('localized_field')
                ? localized_field(
                    array(
                        'cat_name' => $row['cat_name'] ?? '',
                        'cat_name_en' => $row['cat_name_en'] ?? ''
                    ),
                    'cat_name',
                    'cat_name_en',
                    $lang
                )
                : ($row['cat_name'] ?? '')
        );
        $out[] = $item;
    }

    api_success($out);
}*/

if ($route === 'listings') {
    $q = trim((string)($_GET['q'] ?? ''));
    $categorySlug = trim((string)($_GET['category'] ?? ''));
    $city = trim((string)($_GET['city'] ?? ''));
    $sort = trim((string)($_GET['sort'] ?? ''));
    $limit = (int)($_GET['limit'] ?? 20);

    if ($limit < 1) $limit = 20;
    if ($limit > 100) $limit = 100;

    /* تشخیص وجود sort_order برای حفظ رفتار فعلی */
    $has_sort = false;
    try {
        $pdo->query("SELECT sort_order FROM listings LIMIT 1");
        $has_sort = true;
    } catch (Throwable $e) {
        $has_sort = false;
    }

    $sql = "
        SELECT l.*, c.name AS cat_name, c.name_en AS cat_name_en, c.slug AS cat_slug
        FROM listings l
        JOIN categories c ON c.id = l.category_id
        WHERE l.status = 'approved'
    ";

    $params = array();

    if ($categorySlug !== '') {
        $sql .= " AND c.slug = ? ";
        $params[] = $categorySlug;
    }

    if ($city !== '') {
        $sql .= " AND (
            COALESCE(l.city, '') LIKE ? OR
            COALESCE(l.city_en, '') LIKE ?
        ) ";
        $params[] = '%' . $city . '%';
        $params[] = '%' . $city . '%';
    }

    if ($q !== '') {
        $sql .= " AND (
            l.title LIKE ? OR
            l.body LIKE ? OR
            COALESCE(l.title_en, '') LIKE ? OR
            COALESCE(l.body_en, '') LIKE ? OR
            COALESCE(l.city, '') LIKE ? OR
            COALESCE(l.city_en, '') LIKE ?
        ) ";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    /* sort ایمن و whitelist شده */
    if ($sort === 'oldest') {
        $sql .= " ORDER BY l.id ASC ";
    } elseif ($sort === 'popular') {
        $sql .= " ORDER BY COALESCE(l.detail_view_count, 0) DESC, COALESCE(l.list_view_count, 0) DESC, l.id DESC ";
    } elseif ($sort === 'priority' && $has_sort) {
        $sql .= " ORDER BY l.sort_order ASC, l.id DESC ";
    } else {
        /* پیش‌فرض: همان رفتار فعلی پروژه */
        if ($has_sort) {
            $sql .= " ORDER BY l.sort_order ASC, l.id DESC ";
        } else {
            $sql .= " ORDER BY l.id DESC ";
        }
    }

    $sql .= " LIMIT " . (int)$limit;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $out = array();
    foreach ($rows as $row) {
        $item = api_listing_transform($row, $lang);
        $item['category'] = array(
            'slug' => $row['cat_slug'] ?? '',
            'name' => function_exists('localized_field')
                ? localized_field(
                    array(
                        'cat_name' => $row['cat_name'] ?? '',
                        'cat_name_en' => $row['cat_name_en'] ?? ''
                    ),
                    'cat_name',
                    'cat_name_en'
                )
                : ($row['cat_name'] ?? '')
        );
        $out[] = $item;
    }

    api_success(array(
        'items' => $out,
        'filters' => array(
            'q' => $q,
            'category' => $categorySlug,
            'city' => $city,
            'sort' => ($sort !== '' ? $sort : ($has_sort ? 'priority' : 'newest')),
            'limit' => $limit
        ),
        'count' => count($out)
    ));
}

/*
|--------------------------------------------------------------------------
| GET /api/v1/listings/{id}
|--------------------------------------------------------------------------
*/
if (preg_match('#^listings/([0-9]+)$#', $route, $m)) {
    $id = (int)$m[1];

    $st = $pdo->prepare("
        SELECT l.*, c.name AS cat_name, c.name_en AS cat_name_en, c.slug AS cat_slug
        FROM listings l
        JOIN categories c ON c.id = l.category_id
        WHERE l.id = ? AND l.status = 'approved'
        LIMIT 1
    ");
    $st->execute(array($id));
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        api_error('Listing not found', 404);
    }

    $item = api_listing_transform($row, $lang);
    $item['category'] = array(
        'slug' => $row['cat_slug'] ?? '',
        'name' => function_exists('localized_field')
            ? localized_field(
                array(
                    'cat_name' => $row['cat_name'] ?? '',
                    'cat_name_en' => $row['cat_name_en'] ?? ''
                ),
                'cat_name',
                'cat_name_en',
                $lang
            )
            : ($row['cat_name'] ?? '')
    );

    api_success($item);
}

/*
|--------------------------------------------------------------------------
| GET /api/v1/categories/{slug}/listings
|--------------------------------------------------------------------------
*/
if (preg_match('#^categories/([^/]+)/listings$#', $route, $m)) {
    $slug = trim($m[1]);

    $cat = category_by_slug($slug);
    if (!$cat) {
        api_error('Category not found', 404);
    }

    $st = $pdo->prepare("
        SELECT l.*, c.name AS cat_name, c.name_en AS cat_name_en, c.slug AS cat_slug
        FROM listings l
        JOIN categories c ON c.id = l.category_id
        WHERE l.status = 'approved' AND c.slug = ?
        ORDER BY l.id DESC
        LIMIT 100
    ");
    $st->execute(array($slug));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $out = array();
    foreach ($rows as $row) {
        $item = api_listing_transform($row, $lang);
        $item['category'] = array(
            'slug' => $row['cat_slug'] ?? '',
            'name' => function_exists('localized_field')
                ? localized_field(
                    array(
                        'cat_name' => $row['cat_name'] ?? '',
                        'cat_name_en' => $row['cat_name_en'] ?? ''
                    ),
                    'cat_name',
                    'cat_name_en',
                    $lang
                )
                : ($row['cat_name'] ?? '')
        );
        $out[] = $item;
    }

    api_success(array(
        'category' => api_category_transform($cat, $lang),
        'listings' => $out
    ));
}

api_error('Endpoint not found', 404);