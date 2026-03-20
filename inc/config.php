<?php
// ------------------------------
// BASIC SITE CONFIG
// ------------------------------

if (!defined('SITE_NAME')) {
    define('SITE_NAME', getenv('LADIVAR_SITE_NAME') ?: 'نیازمندی‌ها');
}

// اگر سایت داخل فولدر است مقدار را تنظیم کن
// مثال: /ladivar
if (!defined('BASE_URL')) {
    define('BASE_URL', getenv('LADIVAR_BASE_URL') ?: '/ladivar');
}

// ------------------------------
// PATHS
// ------------------------------

if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__));
}

if (!defined('DB_PATH')) {
    define('DB_PATH', getenv('LADIVAR_DB_PATH') ?: (BASE_DIR . '/data.sqlite'));
}

if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', BASE_DIR . '/uploads/');
}
if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', BASE_URL . '/uploads/');
}

// اگر پوشه uploads وجود ندارد بساز
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}

// اگر فایل دیتابیس وجود ندارد بساز
if (!file_exists(DB_PATH)) {
    @touch(DB_PATH);
    @chmod(DB_PATH, 0666);
}

// ------------------------------
// ADMIN LOGIN
// ------------------------------

if (!defined('ADMIN_USER')) {
    define('ADMIN_USER', getenv('LADIVAR_ADMIN_USER') ?: 'admin');
}
if (!defined('ADMIN_PASS')) {
    define('ADMIN_PASS', getenv('LADIVAR_ADMIN_PASS') ?: 'change-this-password');
}

// ------------------------------
// LIMITS
// ------------------------------
if (!defined('MAX_TITLE_LEN')) {
    define('MAX_TITLE_LEN', 120);
}
if (!defined('MAX_BODY_LEN')) {
    define('MAX_BODY_LEN', 1500);
}
if (!defined('MAX_UPLOAD_MB')) {
    define('MAX_UPLOAD_MB', 3); // هر تصویر
}

// ------------------------------
// SESSION
// ------------------------------
if (session_status() === PHP_SESSION_NONE) {

    $sessionPath = (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL : '/';

    $sessionSavePath = BASE_DIR . '/tmp_sessions';
    if (!is_dir($sessionSavePath)) {
        @mkdir($sessionSavePath, 0755, true);
    }

    @session_save_path($sessionSavePath);
    @session_name('LADIVARSESSID');

    session_set_cookie_params(array(
        'lifetime' => 0,
        'path'     => $sessionPath,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ));

    session_start();
}

// ------------------------------
// ERROR MODE (اختیاری برای دیباگ)
// ------------------------------

// برای دیباگ موقتاً روشن کن:
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
