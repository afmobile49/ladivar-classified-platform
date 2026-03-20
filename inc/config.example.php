<?php
// Copy this file to inc/config.php for a fresh local setup.
// You can also override these values with environment variables.

if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'نیازمندی‌ها');
}

// Example: '' for domain root, or '/ladivar' when the app lives in a subfolder.
if (!defined('BASE_URL')) {
    define('BASE_URL', '/ladivar');
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__));
}

// Change this if you want to store the SQLite file elsewhere.
if (!defined('DB_PATH')) {
    define('DB_PATH', BASE_DIR . '/data.sqlite');
}

if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', BASE_DIR . '/uploads/');
}
if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', BASE_URL . '/uploads/');
}

if (!defined('ADMIN_USER')) {
    define('ADMIN_USER', 'admin');
}
if (!defined('ADMIN_PASS')) {
    define('ADMIN_PASS', 'change-this-password');
}

if (!defined('MAX_TITLE_LEN')) {
    define('MAX_TITLE_LEN', 120);
}
if (!defined('MAX_BODY_LEN')) {
    define('MAX_BODY_LEN', 1500);
}
if (!defined('MAX_UPLOAD_MB')) {
    define('MAX_UPLOAD_MB', 3);
}
