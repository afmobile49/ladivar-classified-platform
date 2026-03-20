<?php
// inc/header.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// فقط اتصال DB و ساخت جدول‌ها (اگر db وجود نداشت، خطا نده)
if (function_exists('db')) {
  try { db(); } catch (Exception $e) { /* ignore */ }
}

// دیتاهای لازم برای هدر
$cats = function_exists('categories_all') ? categories_all() : array();
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (!isset($page_title)) $page_title = defined('SITE_NAME') ? SITE_NAME : 'Site';
if (!isset($page_desc))  $page_desc  = 'سایت نیازمندی‌ها با دسته‌بندی‌های مختلف';

/* CSS cache-bust */
$css_ver = '20260303';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo h($page_title); ?></title>
  <meta name="description" content="<?php echo h($page_desc); ?>">
  <link rel="stylesheet" href="<?php echo h(BASE_URL); ?>/assets/style.css?v=<?php echo $css_ver; ?>">
</head>
<body>

<header class="topbar">
  <div class="topbar__inner">
    <div class="brand">
      <a href="<?php echo h(BASE_URL); ?>/index.php"><?php echo h(defined('SITE_NAME') ? SITE_NAME : 'Site'); ?></a>
    </div>

    <form class="search" action="<?php echo h(BASE_URL); ?>/index.php" method="get">
      <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="جستجو در آگهی‌ها…">
      <button type="submit">جستجو</button>
    </form>

    <button class="menuBtn" type="button" aria-label="Menu" aria-expanded="false" aria-controls="mobileMenu">☰</button>

    <nav class="topmenu" id="mobileMenu">
      <a href="<?php echo h(BASE_URL); ?>/post.php" class="btn">ثبت آگهی</a>
      <a href="<?php echo h(BASE_URL); ?>/help.php">Help</a>
      <a href="<?php echo h(BASE_URL); ?>/about.php">About</a>

      <!-- ✅ Settings را از منوی عمومی حذف کردیم (اگر خواستی فقط برای ادمین نمایش بده پایین‌تر گذاشتم) -->

      <!-- ✅ منوی کاربر -->
      <?php if (function_exists('is_user_logged_in') && is_user_logged_in()): ?>
        <a href="<?php echo h(BASE_URL); ?>/my_listings.php">آگهی‌های من</a>
        <a href="<?php echo h(BASE_URL); ?>/logout.php">خروج</a>
      <?php else: ?>
        <a href="<?php echo h(BASE_URL); ?>/login.php">ورود</a>
        <a href="<?php echo h(BASE_URL); ?>/register.php">ثبت‌نام</a>
      <?php endif; ?>

      <!-- ✅ منوی ادمین -->
      <?php if (function_exists('is_admin') && is_admin()): ?>
        <a href="<?php echo h(BASE_URL); ?>/admin/dashboard.php">Admin</a>
        <a href="<?php echo h(BASE_URL); ?>/admin/site_settings.php">Settings</a>
        <a href="<?php echo h(BASE_URL); ?>/admin/logout.php">Logout</a>
      <?php else: ?>
        <!-- Admin Login را از منوی عمومی حذف کردیم -->
      <?php endif; ?>
    </nav>
  </div>

  <div class="catsbar">
    <div class="catsbar__inner">
      <?php foreach ($cats as $c): ?>
        <a class="pill" href="<?php echo h(BASE_URL); ?>/category.php?c=<?php echo urlencode($c['slug']); ?>">
          <?php echo h($c['name']); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</header>

<main class="layout <?php echo !empty($hide_side_ads) ? 'layout--noads' : ''; ?>">

<?php if (empty($hide_side_ads)): ?>
  <aside class="side side--right">
    <?php foreach (function_exists('side_ads') ? side_ads('right') : array() as $ad): ?>
      <div class="adbox">
        <div class="adtitle"><?php echo h($ad['title']); ?></div>
        <div class="adhtml"><?php echo $ad['html']; ?></div>
      </div>
    <?php endforeach; ?>
  </aside>
<?php endif; ?>

<section class="content">