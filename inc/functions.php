<?php
require_once __DIR__ . '/bilingual_helpers.php';


/*
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
*/

/* -------------------------
   Basics
--------------------------*/
function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function now() {
  return gmdate('Y-m-d H:i:s');
}

/* strtolower safe (بدون mbstring هم کار کند) */
function lower_email($email) {
  $email = trim((string)$email);
  if ($email === '') return '';
  if (function_exists('mb_strtolower')) return mb_strtolower($email, 'UTF-8');
  return strtolower($email);
}

/* -------------------------
   DB connection (SQLite)
--------------------------*/
function db() {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $path = defined('DB_PATH') ? DB_PATH : (__DIR__ . '/../data/app.db');

  $dir = dirname($path);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);

  $pdo = new PDO('sqlite:' . $path);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  // ساخت جدول‌ها + مهاجرت‌ها
  db_init($pdo);
  return $pdo;
}

/* -------------------------
   Helpers: check column exists (SQLite)
--------------------------*/
function db_has_column($pdo, $table, $col) {
  try {
    $cols = $pdo->query("PRAGMA table_info(" . $table . ")")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
      if (isset($c['name']) && $c['name'] === $col) return true;
    }
  } catch (Exception $e) {}
  return false;
}

/* -------------------------
   Ensure tables exist
   ✅ compatible: db_init() or db_init($pdo)
--------------------------*/
function db_init($pdo = null) {
  static $inited = false;
  if ($inited) return; // جلوگیری از اجراهای تکراری/حلقه‌ای
  $inited = true;

  if (!$pdo instanceof PDO) {
    // اگر بدون آرگومان صدا زده شد
    $pdo = db();
    return;
  }

  // categories
  $pdo->exec("CREATE TABLE IF NOT EXISTS categories(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL
  )");

  // listings
  $pdo->exec("CREATE TABLE IF NOT EXISTS listings(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    city TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    approved_at TEXT,
    user_id INTEGER,
    edit_token TEXT
  )");

  // listing_images
  $pdo->exec("CREATE TABLE IF NOT EXISTS listing_images(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    listing_id INTEGER NOT NULL,
    path TEXT NOT NULL,
    created_at TEXT NOT NULL
  )");

  // settings
  $pdo->exec("CREATE TABLE IF NOT EXISTS settings(
    k TEXT PRIMARY KEY,
    v TEXT NOT NULL
  )");

  // side_ads
  $pdo->exec("CREATE TABLE IF NOT EXISTS side_ads(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    position TEXT NOT NULL,
    title TEXT NOT NULL DEFAULT '',
    html TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
  )");

  // users (profile by email)  — ممکنه نسخه قدیمی بدون is_active باشد
  $pdo->exec("CREATE TABLE IF NOT EXISTS users(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL
  )");

  // ✅ Migration: users.is_active اگر نبود اضافه کن
  if (!db_has_column($pdo, 'users', 'is_active')) {
    try {
      $pdo->exec("ALTER TABLE users ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1");
    } catch (Exception $e) {
      // ignore
    }
  }

  // admins (admin panel)
  $pdo->exec("CREATE TABLE IF NOT EXISTS admins(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL
  )");

  // defaults settings
  set_setting_if_empty('admin_phone', '0900-000-0000', $pdo);
  set_setting_if_empty('admin_email', 'info@example.com', $pdo);
  set_setting_if_empty('help_text', "راهنمای استفاده از سایت...\n", $pdo);
  set_setting_if_empty('about_text', "درباره ما...\n", $pdo);
  set_setting_if_empty('footer_help', "برای راهنمایی به صفحه Help مراجعه کنید.", $pdo);


  //---------------------------
  if (!db_has_column($pdo, 'listings', 'list_view_count')) {
    try {
      $pdo->exec("ALTER TABLE listings ADD COLUMN list_view_count INTEGER NOT NULL DEFAULT 0");
    } catch (Exception $e) {
      // ignore
    }
  }

  if (!db_has_column($pdo, 'listings', 'detail_view_count')) {
    try {
      $pdo->exec("ALTER TABLE listings ADD COLUMN detail_view_count INTEGER NOT NULL DEFAULT 0");
    } catch (Exception $e) {
      // ignore
    }
  }
  //--------------------------

  // seed categories if empty
  $cnt = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
  if ($cnt === 0) {
    $st = $pdo->prepare("INSERT INTO categories(name,slug,created_at) VALUES(?,?,?)");
    $defaults = array(
      array('خدمات', 'services'),
      array('املاک', 'real-estate'),
      array('خودرو', 'cars'),
      array('استخدام', 'jobs'),
    );
    foreach ($defaults as $c) {
      $st->execute(array($c[0], $c[1], now()));
    }
  }

  // seed admin if empty (from config constants)
  $ac = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
  if ($ac === 0 && defined('ADMIN_USER') && defined('ADMIN_PASS')) {
    $hash = password_hash((string)ADMIN_PASS, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO admins(username,password_hash,created_at) VALUES(?,?,?)")
        ->execute(array((string)ADMIN_USER, $hash, now()));
  }
}

/* -------------------------
   Settings
--------------------------*/
function set_setting_if_empty($k, $v, $pdo = null) {
  if (!$pdo instanceof PDO) $pdo = db();
  $k = (string)$k; $v = (string)$v;

  $st = $pdo->prepare("SELECT v FROM settings WHERE k=?");
  $st->execute(array($k));
  $cur = $st->fetchColumn();
  if ($cur === false) {
    $pdo->prepare("INSERT INTO settings(k,v) VALUES(?,?)")->execute(array($k, $v));
  }
}

function set_setting($k, $v) {
  $pdo = db();
  $k = (string)$k;
  $v = (string)$v;
  $pdo->prepare("INSERT OR REPLACE INTO settings(k,v) VALUES(?,?)")->execute(array($k, $v));
}

function get_setting($k, $default='') {
  $pdo = db();
  $st = $pdo->prepare("SELECT v FROM settings WHERE k=?");
  $st->execute(array((string)$k));
  $v = $st->fetchColumn();
  return ($v === false) ? (string)$default : (string)$v;
}

/* -------------------------
   Categories
--------------------------*/
function categories_all() {
  return db()->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll();
}


//----------------------------------------- for counter
function record_listing_impressions($ids) {
  $pdo = db();

  if (!is_array($ids) || !count($ids)) return;

  $clean = array();
  foreach ($ids as $id) {
    $id = (int)$id;
    if ($id > 0) $clean[] = $id;
  }

  $clean = array_values(array_unique($clean));
  if (!count($clean)) return;

  $placeholders = implode(',', array_fill(0, count($clean), '?'));
  $sql = "UPDATE listings
          SET list_view_count = COALESCE(list_view_count, 0) + 1
          WHERE id IN ($placeholders)";
  $st = $pdo->prepare($sql);
  $st->execute($clean);
}

//-----------------------------------------------
function listing_view_stats($listing_id) {
  $st = db()->prepare("
    SELECT
      COALESCE(list_view_count, 0) AS list_view_count,
      COALESCE(detail_view_count, 0) AS detail_view_count
    FROM listings
    WHERE id = ?
    LIMIT 1
  ");
  $st->execute(array((int)$listing_id));
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    return array(
      'list_view_count' => 0,
      'detail_view_count' => 0
    );
  }

  return $row;
}

//------------------------------
function record_listing_detail_view($listing_id) {
  $listing_id = (int)$listing_id;
  if ($listing_id <= 0) return;

  $st = db()->prepare("
    UPDATE listings
    SET detail_view_count = COALESCE(detail_view_count, 0) + 1
    WHERE id = ?
  ");
  $st->execute(array($listing_id));
}

/* -------------------------
   Listing images
--------------------------*/
/*
function listing_images($listing_id) {
  $st = db()->prepare("SELECT * FROM listing_images WHERE listing_id=? ORDER BY id ASC");
  $st->execute(array((int)$listing_id));
  return $st->fetchAll();
}*/

function listing_images($listing_id) {
  $pdo = db();
  $hasSortOrder = db_has_column($pdo, 'listing_images', 'sort_order');

  $sql = $hasSortOrder
    ? "SELECT * FROM listing_images WHERE listing_id=? ORDER BY CASE WHEN COALESCE(sort_order,0)=0 THEN id ELSE sort_order END ASC, id ASC"
    : "SELECT * FROM listing_images WHERE listing_id=? ORDER BY id ASC";

  $st = $pdo->prepare($sql);
  $st->execute(array((int)$listing_id));
  return $st->fetchAll(PDO::FETCH_ASSOC);
}
//--------------------------------
/*
function listing_images_lang($listing_id, $lang = null) {
    $lang = $lang ?: current_lang();
    $pdo = db();

    if ($lang === 'en') {
        $stmt = $pdo->prepare("
            SELECT *
            FROM listing_images
            WHERE listing_id = ?
              AND COALESCE(lang_scope, 'both') IN ('both', 'en')
            ORDER BY id ASC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT *
            FROM listing_images
            WHERE listing_id = ?
              AND COALESCE(lang_scope, 'both') IN ('both', 'fa')
            ORDER BY id ASC
        ");
    }

    $stmt->execute([(int)$listing_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}*/

function listing_images_lang($listing_id, $lang = null) {
    $lang = $lang ?: (function_exists('current_lang') ? current_lang() : 'fa');
    $pdo = db();

    $hasSortOrder = db_has_column($pdo, 'listing_images', 'sort_order');

    $orderSql = $hasSortOrder
        ? "ORDER BY CASE WHEN COALESCE(sort_order,0)=0 THEN id ELSE sort_order END ASC, id ASC"
        : "ORDER BY id ASC";

    if ($lang === 'en') {
        $stmt = $pdo->prepare("
            SELECT *
            FROM listing_images
            WHERE listing_id = ?
              AND COALESCE(lang_scope, 'both') IN ('both', 'en')
            $orderSql
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT *
            FROM listing_images
            WHERE listing_id = ?
              AND COALESCE(lang_scope, 'both') IN ('both', 'fa')
            $orderSql
        ");
    }

    $stmt->execute([(int)$listing_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/* -------------------------
   Side ads
--------------------------*/
/*
function side_ads($pos) {
  $st = db()->prepare("SELECT * FROM side_ads WHERE position=? AND is_active=1 ORDER BY sort_order ASC, id DESC");
  $st->execute(array((string)$pos));
  return $st->fetchAll();
}*/

function side_ads($pos) {
  $pdo = db();

  $hasTitleEn     = db_has_column($pdo, 'side_ads', 'title_en');
  $hasHtmlEn      = db_has_column($pdo, 'side_ads', 'html_en');
  $hasImagePath   = db_has_column($pdo, 'side_ads', 'image_path');
  $hasImagePathEn = db_has_column($pdo, 'side_ads', 'image_path_en');
  $hasImageScope  = db_has_column($pdo, 'side_ads', 'image_scope');

  $select = "id, position, title, html, is_active, sort_order, created_at";
  if ($hasTitleEn)     $select .= ", COALESCE(title_en, '') AS title_en";
  if ($hasHtmlEn)      $select .= ", COALESCE(html_en, '') AS html_en";
  if ($hasImagePath)   $select .= ", COALESCE(image_path, '') AS image_path";
  if ($hasImagePathEn) $select .= ", COALESCE(image_path_en, '') AS image_path_en";
  if ($hasImageScope)  $select .= ", COALESCE(image_scope, 'both') AS image_scope";

  $st = $pdo->prepare("
    SELECT $select
    FROM side_ads
    WHERE position=? AND is_active=1
    ORDER BY sort_order ASC, id DESC
  ");
  $st->execute(array((string)$pos));
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $out = array();

  foreach ($rows as $row) {
    $scope = $hasImageScope ? trim((string)($row['image_scope'] ?? 'both')) : 'both';
    if (!in_array($scope, array('both', 'fa', 'en'), true)) {
      $scope = 'both';
    }

    $isEnglish = function_exists('is_en') && is_en();

    // کل تبلیغ بر اساس scope فیلتر شود
    if ($isEnglish && $scope === 'fa') {
      continue;
    }
    if (!$isEnglish && $scope === 'en') {
      continue;
    }

    $row['title_display'] = $row['title'] ?? '';
    $row['html_display']  = $row['html'] ?? '';
    $row['image_display'] = '';

    if ($isEnglish) {
      if ($hasTitleEn && trim((string)($row['title_en'] ?? '')) !== '') {
        $row['title_display'] = $row['title_en'];
      }
      if ($hasHtmlEn && trim((string)($row['html_en'] ?? '')) !== '') {
        $row['html_display'] = $row['html_en'];
      }
    }

    $imgFa = $hasImagePath ? trim((string)($row['image_path'] ?? '')) : '';
    $imgEn = $hasImagePathEn ? trim((string)($row['image_path_en'] ?? '')) : '';

    if ($isEnglish) {
      if ($scope === 'en') {
        $row['image_display'] = $imgEn;
      } else { // both
        $row['image_display'] = ($imgEn !== '') ? $imgEn : $imgFa;
      }
    } else {
      if ($scope === 'fa') {
        $row['image_display'] = $imgFa;
      } else { // both
        $row['image_display'] = ($imgFa !== '') ? $imgFa : $imgEn;
      }
    }

    $out[] = $row;
  }

  return $out;
}


/* -------------------------
   Admin auth (session)
--------------------------*/
function is_admin() {
  return !empty($_SESSION['admin_id']) || !empty($_SESSION['is_admin']);
}

function require_admin() {
  if (!is_admin()) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
  }
}

function admin_login($username, $password) {
  $u = trim((string)$username);
  $p = (string)$password;
  if ($u === '' || $p === '') return false;

  $pdo = db();
  $st = $pdo->prepare("SELECT id, password_hash FROM admins WHERE username=? LIMIT 1");
  $st->execute(array($u));
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) return false;

  if (!password_verify($p, (string)$row['password_hash'])) return false;

  if (function_exists('session_regenerate_id')) @session_regenerate_id(true);

  $_SESSION['admin_id'] = (int)$row['id'];
  $_SESSION['is_admin'] = 1;
  return true;
}

function admin_logout() {
  unset($_SESSION['admin_id'], $_SESSION['is_admin']);
}

/* -------------------------
   User auth (profile by email)
--------------------------*/
function is_user_logged_in() {
  return !empty($_SESSION['user_id']);
}

function user_register($email, $pass) {
  $email = lower_email($email);
  $pass  = (string)$pass;

  if ($email === '' || $pass === '') return array(false, 'ایمیل و رمز عبور الزامی است.');
  if (strlen($pass) < 6) return array(false, 'رمز عبور باید حداقل ۶ کاراکتر باشد.');
  if (strpos($email, '@') === false) return array(false, 'ایمیل معتبر نیست.');

  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $pdo = db();

  // اگر ستون is_active نبود، INSERT را بدون آن انجام بده
  $hasIsActive = db_has_column($pdo, 'users', 'is_active');

  try {
    if ($hasIsActive) {
      $pdo->prepare("INSERT INTO users(email,password_hash,created_at,is_active) VALUES(?,?,?,1)")
          ->execute(array($email, $hash, now()));
    } else {
      $pdo->prepare("INSERT INTO users(email,password_hash,created_at) VALUES(?,?,?)")
          ->execute(array($email, $hash, now()));
    }
    return array(true, '');
  } catch (Exception $e) {
    return array(false, 'این ایمیل قبلاً ثبت شده است.');
  }
}

function user_login($email, $pass) {
  $email = lower_email($email);
  $pass  = (string)$pass;
  if ($email === '' || $pass === '') return false;

  $pdo = db();
  $hasIsActive = db_has_column($pdo, 'users', 'is_active');

  $sql = $hasIsActive
    ? "SELECT id, password_hash, is_active FROM users WHERE email=? LIMIT 1"
    : "SELECT id, password_hash, 1 AS is_active FROM users WHERE email=? LIMIT 1";

  $st = $pdo->prepare($sql);
  $st->execute(array($email));
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) return false;

  if ((int)$row['is_active'] !== 1) return false;
  if (!password_verify($pass, (string)$row['password_hash'])) return false;

  if (function_exists('session_regenerate_id')) @session_regenerate_id(true);

  $_SESSION['user_id'] = (int)$row['id'];
  $_SESSION['user_email'] = $email;
  return true;
}

function user_logout() {
  unset($_SESSION['user_id'], $_SESSION['user_email']);
}

/* -------------------------
   Guest edit token helpers
--------------------------*/
function make_edit_token($bytes = 16) {
  $bytes = (int)$bytes;
  if ($bytes < 8) $bytes = 8;

  if (function_exists('random_bytes')) {
    return bin2hex(random_bytes($bytes));
  }

  // fallback (not cryptographically strong, but ok for temporary link)
  $s = '';
  for ($i=0; $i<$bytes*2; $i++) {
    $s .= dechex(mt_rand(0, 15));
  }
  return $s;
}

/* -------------------------
   Upload helpers
--------------------------*/
function validate_image_upload($f) {
  if (!isset($f['error']) || is_array($f['error'])) return 'آپلود نامعتبر است.';
  if ($f['error'] !== UPLOAD_ERR_OK) return 'خطا در آپلود تصویر.';
  $max = defined('MAX_UPLOAD_MB') ? (MAX_UPLOAD_MB * 1024 * 1024) : (2*1024*1024);
  if ((int)$f['size'] > $max) return 'حجم تصویر زیاد است.';

  $tmp = isset($f['tmp_name']) ? $f['tmp_name'] : '';
  $info = @getimagesize($tmp);
  if (!$info) return 'فایل تصویر معتبر نیست.';

  $mime = isset($info['mime']) ? $info['mime'] : '';
  $allowed = array('image/jpeg','image/png','image/webp');
  if (!in_array($mime, $allowed, true)) return 'فرمت مجاز نیست (jpg/png/webp).';
  return '';
}

function save_uploaded_image($f) {
  if (!defined('UPLOAD_DIR')) return null;
  if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

  $tmp = isset($f['tmp_name']) ? $f['tmp_name'] : '';
  $info = @getimagesize($tmp);
  if (!$info) return null;

  $mime = isset($info['mime']) ? $info['mime'] : 'image/jpeg';
  $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');

  $rand = function_exists('random_bytes')
    ? bin2hex(random_bytes(6))
    : substr(md5(uniqid('', true)), 0, 12);

  $name = date('Ymd_His') . '_' . $rand . '.' . $ext;
  $dest = rtrim(UPLOAD_DIR, '/') . '/' . $name;

  if (!@move_uploaded_file($tmp, $dest)) return null;
  return $name;
}

//------------------------------------------------------------------------------
function category_by_slug($slug) {
  $slug = trim((string)$slug);
  if ($slug === '') return null;

  $st = db()->prepare("SELECT * FROM categories WHERE slug=? LIMIT 1");
  $st->execute(array($slug));
  $row = $st->fetch(PDO::FETCH_ASSOC);

  return $row ? $row : null;
}
//------------------------------------------------------------------------------
function slugify($text) {
  $text = trim((string)$text);
  if ($text === '') {
    return 'cat-' . time();
  }

  // اگر حروف انگلیسی داشت
  $slug = strtolower($text);
  $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
  $slug = trim($slug, '-');

  // اگر خروجی خالی شد (مثلاً متن فارسی بود)
  if ($slug === '') {
    $slug = 'cat-' . time();
  }

  return $slug;
}

//-----------------------------------------------------------------------------
function base_site_url() {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
  return $scheme . '://' . $host . BASE_URL;
}

function category_url($slug) {
  return BASE_URL . '/cat/' . urlencode((string)$slug);
}

function listing_url($id) {
  return BASE_URL . '/ad/' . (int)$id;
}

function listing_full_url($id) {
  return base_site_url() . '/ad/' . (int)$id;
}
//------------------------------------------------------------------------------
if (!function_exists('my_listings')) {
  function my_listings() {
    if (empty($_SESSION['user_id'])) {
      return array();
    }

    $pdo = db();

    $hasTitleEn = db_has_column($pdo, 'listings', 'title_en');
    $hasBodyEn  = db_has_column($pdo, 'listings', 'body_en');
    $hasCityEn  = db_has_column($pdo, 'listings', 'city_en');
    $hasSource  = db_has_column($pdo, 'listings', 'source_lang');

    $select = "l.*";
    if ($hasTitleEn) $select .= ", COALESCE(l.title_en, '') AS title_en";
    if ($hasBodyEn)  $select .= ", COALESCE(l.body_en, '') AS body_en";
    if ($hasCityEn)  $select .= ", COALESCE(l.city_en, '') AS city_en";
    if ($hasSource)  $select .= ", COALESCE(l.source_lang, 'fa') AS source_lang";

    $st = $pdo->prepare("
      SELECT $select
      FROM listings l
      WHERE l.user_id = ?
      ORDER BY l.id DESC
    ");
    $st->execute(array((int)$_SESSION['user_id']));
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
//------------------------------------------------------------------------------
/*
if (!function_exists('my_listing_by_id')) {
  function my_listing_by_id($id) {
    if (empty($_SESSION['user_id'])) {
      return null;
    }

    $pdo = db();
    $st = $pdo->prepare("
      SELECT *
      FROM listings
      WHERE id = ? AND user_id = ?
      LIMIT 1
    ");
    $st->execute(array((int)$id, (int)$_SESSION['user_id']));
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ? $row : null;
  }
}*/

if (!function_exists('my_listing_by_id')) {
  function my_listing_by_id($id) {
    if (empty($_SESSION['user_id'])) {
      return null;
    }

    $pdo = db();

    $hasTitleEn = db_has_column($pdo, 'listings', 'title_en');
    $hasBodyEn  = db_has_column($pdo, 'listings', 'body_en');
    $hasCityEn  = db_has_column($pdo, 'listings', 'city_en');
    $hasSource  = db_has_column($pdo, 'listings', 'source_lang');

    $select = "l.*";
    if ($hasTitleEn) $select .= ", COALESCE(l.title_en, '') AS title_en";
    if ($hasBodyEn)  $select .= ", COALESCE(l.body_en, '') AS body_en";
    if ($hasCityEn)  $select .= ", COALESCE(l.city_en, '') AS city_en";
    if ($hasSource)  $select .= ", COALESCE(l.source_lang, 'fa') AS source_lang";

    $st = $pdo->prepare("
      SELECT $select
      FROM listings l
      WHERE l.id = ? AND l.user_id = ?
      LIMIT 1
    ");
    $st->execute(array((int)$id, (int)$_SESSION['user_id']));
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ? $row : null;
  }
}
//------------------------------------------------------------------------------
if (!function_exists('update_my_listing')) {
  function update_my_listing($id, $data) {
    if (empty($_SESSION['user_id'])) {
      return false;
    }

    $pdo = db();

    $st = $pdo->prepare("
      SELECT id
      FROM listings
      WHERE id = ? AND user_id = ?
      LIMIT 1
    ");
    $st->execute(array((int)$id, (int)$_SESSION['user_id']));
    $exists = $st->fetch(PDO::FETCH_ASSOC);

    if (!$exists) {
      return false;
    }

    $fields = array();
    $values = array();

    foreach (array('category_id','title','body','city','title_en','body_en','city_en') as $col) {
      if (array_key_exists($col, $data) && db_has_column($pdo, 'listings', $col)) {
        $fields[] = $col . ' = ?';
        $values[] = $data[$col];
      }
    }

    if (!count($fields)) {
      return false;
    }

    $values[] = (int)$id;
    $values[] = (int)$_SESSION['user_id'];

    $sql = "UPDATE listings SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
    $upd = $pdo->prepare($sql);

    return $upd->execute($values);
  }
}
//------------------------------------------------------------------------------
if (!function_exists('find_listing_by_manage_code')) {
  function find_listing_by_manage_code($code) {
    $code = trim((string)$code);
    if ($code === '') return null;

    $st = db()->prepare("
      SELECT *
      FROM listings
      WHERE edit_token = ?
      LIMIT 1
    ");
    $st->execute(array($code));
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ? $row : null;
  }
}
//-----------------------------------------------------------------------------
if (!function_exists('find_listing_by_manage_code_and_id')) {
  function find_listing_by_manage_code_and_id($id, $code) {
    $code = trim((string)$code);
    if ((int)$id <= 0 || $code === '') return null;

    $st = db()->prepare("
      SELECT *
      FROM listings
      WHERE id = ? AND edit_token = ?
      LIMIT 1
    ");
    $st->execute(array((int)$id, $code));
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ? $row : null;
  }
}
//-----------------------------------------------------------------------------
if (!function_exists('update_listing_by_manage_code')) {
  function update_listing_by_manage_code($id, $code, $data) {
    $code = trim((string)$code);
    if ((int)$id <= 0 || $code === '') return false;

    $pdo = db();

    $st = $pdo->prepare("
      SELECT id
      FROM listings
      WHERE id = ? AND edit_token = ?
      LIMIT 1
    ");
    $st->execute(array((int)$id, $code));
    $exists = $st->fetch(PDO::FETCH_ASSOC);

    if (!$exists) return false;

    $fields = array();
    $values = array();

    foreach (array('category_id','title','body','city','title_en','body_en','city_en') as $col) {
      if (array_key_exists($col, $data) && db_has_column($pdo, 'listings', $col)) {
        $fields[] = $col . ' = ?';
        $values[] = $data[$col];
      }
    }

    if (!count($fields)) return false;

    $values[] = (int)$id;
    $values[] = $code;

    $sql = "UPDATE listings SET " . implode(', ', $fields) . " WHERE id = ? AND edit_token = ?";
    $upd = $pdo->prepare($sql);
    return $upd->execute($values);
  }
}
//------------------------------------------------------------------------------
function listings_search_filters_from_request() {
  $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
  $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

  if ($page < 1) $page = 1;
  if ($perPage < 1) $perPage = 20;
  if ($perPage > 100) $perPage = 100;

  return array(
    'q'         => trim((string)($_GET['q'] ?? '')),
    'category'  => trim((string)($_GET['category'] ?? '')),
    'city'      => trim((string)($_GET['city'] ?? '')),
    'sort'      => trim((string)($_GET['sort'] ?? 'newest')),
    'page'      => $page,
    'per_page'  => $perPage,
  );
}
//----------------------------------
function listings_build_search_sql($filters = array(), $countOnly = false) {
  $pdo = db();

  $q        = trim((string)($filters['q'] ?? ''));
  $category = trim((string)($filters['category'] ?? ''));
  $city     = trim((string)($filters['city'] ?? ''));
  $sort     = trim((string)($filters['sort'] ?? 'newest'));
  $page     = (int)($filters['page'] ?? 1);
  $perPage  = (int)($filters['per_page'] ?? 20);

  if ($page < 1) $page = 1;
  if ($perPage < 1) $perPage = 20;
  if ($perPage > 100) $perPage = 100;

  $params = array();

  if ($countOnly) {
    $sql = "
      SELECT COUNT(*) AS cnt
      FROM listings l
      JOIN categories c ON c.id = l.category_id
      WHERE l.status = 'approved'
    ";
  } else {
    $sql = "
      SELECT
        l.*,
        c.name AS cat_name,
        c.name_en AS cat_name_en,
        c.slug AS cat_slug
      FROM listings l
      JOIN categories c ON c.id = l.category_id
      WHERE l.status = 'approved'
    ";
  }

  if ($category !== '') {
    $sql .= " AND c.slug = ? ";
    $params[] = $category;
  }

  if ($city !== '') {
    $sql .= " AND (
      l.city LIKE ? OR COALESCE(l.city_en, '') LIKE ?
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

  if (!$countOnly) {
    switch ($sort) {
      case 'oldest':
        $sql .= " ORDER BY l.id ASC ";
        break;

      case 'popular':
        $sql .= " ORDER BY COALESCE(l.detail_view_count, 0) DESC, COALESCE(l.list_view_count, 0) DESC, l.id DESC ";
        break;

      case 'priority':
        $sql .= " ORDER BY COALESCE(l.sort_order, 0) ASC, l.id DESC ";
        break;

      case 'newest':
      default:
        $sql .= " ORDER BY l.id DESC ";
        break;
    }

    $offset = ($page - 1) * $perPage;
    $sql .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
  }

  return array($sql, $params);
}

function listings_search($filters = array()) {
  list($sql, $params) = listings_build_search_sql($filters, false);
  $st = db()->prepare($sql);
  $st->execute($params);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

function listings_search_count($filters = array()) {
  list($sql, $params) = listings_build_search_sql($filters, true);
  $st = db()->prepare($sql);
  $st->execute($params);
  return (int)$st->fetchColumn();
}
//------------------------------------------------------------------------------
