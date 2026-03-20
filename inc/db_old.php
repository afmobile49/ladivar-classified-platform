<?php
require_once __DIR__ . '/config.php';

function db() {
  static $pdo = null;
  if ($pdo) return $pdo;

  $pdo = new PDO('sqlite:' . DB_PATH);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec("PRAGMA foreign_keys = ON;");
  return $pdo;
}

function db_init() {
  $pdo = db();

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS categories (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      slug TEXT NOT NULL UNIQUE,
      sort_order INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL
    );
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS listings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      category_id INTEGER NOT NULL,
      title TEXT NOT NULL,
      body TEXT NOT NULL,
      city TEXT DEFAULT '',
      status TEXT NOT NULL DEFAULT 'pending', -- pending|approved|rejected
      created_at TEXT NOT NULL,
      approved_at TEXT,
      FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE
    );
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS listing_images (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      listing_id INTEGER NOT NULL,
      path TEXT NOT NULL,
      created_at TEXT NOT NULL,
      FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE
    );
  ");

  // تبلیغات کناری (راست/چپ)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS side_ads (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      position TEXT NOT NULL, -- right|left
      title TEXT NOT NULL DEFAULT '',
      html TEXT NOT NULL,
      is_active INTEGER NOT NULL DEFAULT 1,
      sort_order INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL
    );
  ");

  // تنظیمات سایت
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS site_settings (
      k TEXT PRIMARY KEY,
      v TEXT NOT NULL
    );
  ");

  // تنظیمات پیش‌فرض
  $defaults = array(
    'admin_phone' => '0900-000-0000',
    'admin_email' => 'info@example.com',
    'footer_help' => 'برای راهنمایی به صفحه Help مراجعه کنید.',
    'about_text'  => 'متن درباره ما را از پنل ادمین تنظیم کنید.',
    'help_text'   => 'متن راهنما را از پنل ادمین تنظیم کنید.'
  );

  foreach ($defaults as $k => $v) {
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO site_settings(k,v) VALUES(?,?)");
    $stmt->execute(array($k, $v));
  }
}