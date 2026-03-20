<?php
// inc/header.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// اتصال دیتابیس بدون ایجاد خطا در صورت مشکل
if (function_exists('db')) {
    try {
        db();
    } catch (Exception $e) {
        // ignore
    }
}

// داده‌های هدر
$cats = function_exists('categories_all') ? categories_all() : array();
$q    = isset($_GET['q']) ? trim($_GET['q']) : '';

// نام سایت
$site_name = defined('SITE_NAME') ? SITE_NAME : 'Site';
if (function_exists('t')) {
    $site_name = t('site_name');
}

// مقادیر پیش‌فرض SEO
if (!isset($page_title) || $page_title === '') {
    $page_title = $site_name;
}

if (!isset($page_desc) || $page_desc === '') {
    $page_desc = is_en() ? 'Classifieds website with multiple categories.' : 'سایت نیازمندی‌ها با دسته‌بندی‌های مختلف';
}

// آدرس فعلی برای canonical و og:url
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

if (!isset($current_url) || $current_url === '') {
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $current_url = $scheme . '://' . $host . $uri;
}

/* برای صفحات جستجو canonical را به صفحه اصلی همان زبان برگردان */
//if (!empty($_GET['q'])) {
//    $current_url = $scheme . '://' . $host . append_lang_to_url(BASE_URL . '/');
//}

/* برای صفحات فیلتر/جستجو canonical را به صفحه اصلی همان زبان برگردان */
if (!empty($_GET['q']) || !empty($_GET['category']) || !empty($_GET['city'])) {
    $current_url = $scheme . '://' . $host . append_lang_to_url(BASE_URL . '/');
}

// نسخه فایل CSS
$css_ver = '20260317-bilingual-1';

// لینک زبان مقابل
$fa_url = switch_lang_url('fa');
$en_url = switch_lang_url('en');
?>
<!doctype html>
<html lang="<?php echo h(page_lang_attr()); ?>" dir="<?php echo h(page_dir()); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?php echo h($page_title); ?></title>
  <meta name="description" content="<?php echo h($page_desc); ?>">

  <?php if (!empty($meta_robots)): ?>
    <meta name="robots" content="<?php echo h($meta_robots); ?>">
  <?php endif; ?>

  <link rel="canonical" href="<?php echo h($current_url); ?>">
  <link rel="alternate" hreflang="fa" href="<?php echo h($fa_url); ?>">
  <link rel="alternate" hreflang="en" href="<?php echo h($en_url); ?>">
  <link rel="alternate" hreflang="x-default" href="<?php echo h($fa_url); ?>">

  <meta property="og:locale" content="<?php echo h(locale_code()); ?>">
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?php echo h($page_title); ?>">
  <meta property="og:description" content="<?php echo h($page_desc); ?>">
  <meta property="og:url" content="<?php echo h($current_url); ?>">
  <meta property="og:site_name" content="<?php echo h($site_name); ?>">

  <?php if (!empty($og_image)): ?>
    <meta property="og:image" content="<?php echo h($og_image); ?>">
    <meta property="og:image:alt" content="<?php echo h($page_title); ?>">
    <meta name="twitter:image" content="<?php echo h($og_image); ?>">
    <meta name="twitter:card" content="summary_large_image">
  <?php endif; ?>

  <meta name="twitter:title" content="<?php echo h($page_title); ?>">
  <meta name="twitter:description" content="<?php echo h($page_desc); ?>">

  <link rel="stylesheet" href="<?php echo h(BASE_URL); ?>/assets/style.css?v=<?php echo h($css_ver); ?>">
</head>
<body>

<header class="topbar">
  <div class="topbar__inner">
    <div class="brand">
      <a href="<?php echo h(append_lang_to_url(BASE_URL . '/')); ?>"><?php echo h($site_name); ?></a>
    </div>

    <form class="search" action="<?php echo h(BASE_URL); ?>/" method="get">
      <input
        type="text"
        name="q"
        value="<?php echo h($q); ?>"
        placeholder="<?php echo h(t('search_placeholder')); ?>"
      >
      <input type="hidden" name="lang" value="<?php echo h(current_lang()); ?>">
      <button type="submit"><?php echo h(t('search')); ?></button>
    </form>

    <button
      class="menuBtn"
      type="button"
      aria-label="Menu"
      aria-expanded="false"
      aria-controls="mobileMenu"
    >☰</button>

    <nav class="topmenu" id="mobileMenu">
      <a href="<?php echo h(append_lang_to_url(BASE_URL . '/post.php')); ?>" class="btn"><?php echo h(t('post_ad')); ?></a>
      <a href="<?php echo h(append_lang_to_url(BASE_URL . '/help.php')); ?>"><?php echo h(t('help')); ?></a>
      <a href="<?php echo h(append_lang_to_url(BASE_URL . '/about.php')); ?>"><?php echo h(t('about')); ?></a>

      <a href="<?php echo h($fa_url); ?>" class="tag">فارسی</a>
      <a href="<?php echo h($en_url); ?>" class="tag">English</a>

      <?php if (function_exists('is_user_logged_in') && is_user_logged_in()): ?>
        <a href="<?php echo h(append_lang_to_url(BASE_URL . '/my_listings.php')); ?>"><?php echo h(t('my_listings')); ?></a>
        <a href="<?php echo h(BASE_URL); ?>/logout.php"><?php echo h(t('logout')); ?></a>
      <?php else: ?>
        <a href="<?php echo h(append_lang_to_url(BASE_URL . '/login.php')); ?>"><?php echo h(t('login')); ?></a>
        <a href="<?php echo h(append_lang_to_url(BASE_URL . '/register.php')); ?>"><?php echo h(t('register')); ?></a>
      <?php endif; ?>

      <?php if (function_exists('is_admin') && is_admin()): ?>
        <a href="<?php echo h(BASE_URL); ?>/admin/dashboard.php">مدیریت</a>
        <a href="<?php echo h(BASE_URL); ?>/admin/site_settings.php">تنظیمات</a>
        <a href="<?php echo h(BASE_URL); ?>/admin/logout.php">خروج</a>
      <?php endif; ?>
    </nav>
  </div>

  <div class="catsbar">
    <div class="catsbar__inner">
      <?php foreach ($cats as $c): ?>
        <a class="pill" href="<?php echo h(category_url($c['slug'])); ?>">
          <?php echo h(localized_field($c, 'name')); ?>
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

  <?php if (!empty($ad['title_display'])): ?>
    <div class="adtitle">
      <?php echo h($ad['title_display']); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($ad['image_display'])): ?>
    <img
      src="<?php echo h(BASE_URL . '/uploads/ads/' . $ad['image_display']); ?>"
      alt="<?php echo h($ad['title_display'] ?? ''); ?>"
      style="width:100%;height:auto;border-radius:12px;display:block;margin:8px 0;"
    >
  <?php endif; ?>

  <?php if (!empty($ad['html_display'])): ?>
    <div class="adhtml">
      <?php echo $ad['html_display']; ?>
    </div>
  <?php endif; ?>

</div>

<?php endforeach; ?>


  </aside>
<?php endif; ?>

<section class="content">