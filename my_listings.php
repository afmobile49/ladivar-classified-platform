<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
    header('Location: ' . append_lang_to_url(BASE_URL . '/login.php'));
    exit;
}

$page_title = t('my_listings') . ' | ' . t('site_name');
$page_desc  = is_en() ? 'Manage your submitted listings.' : 'آگهی‌های ثبت‌شده خود را مدیریت کنید.';

$items = function_exists('my_listings') ? my_listings() : array();

require_once __DIR__ . '/inc/header.php';
?>

<h1 class="h1"><?php echo h(t('my_listings')); ?></h1>

<div class="listings">
  <?php foreach ($items as $it): ?>
    <?php
      //$imgs = listing_images($it['id']);
      $imgs = function_exists('listing_images_lang')? listing_images_lang($it['id'], current_lang()) : listing_images($it['id']);
      $thumb = count($imgs) ? (UPLOAD_URL . $imgs[0]['path']) : (BASE_URL . '/assets/noimg.png');
      $titleText = localized_field($it, 'title');
      $cityText  = localized_field($it, 'city');
      $status    = $it['status'] ?? '';
    ?>
    <div class="card">
      <div class="thumb">
        <img src="<?php echo h($thumb); ?>" alt="<?php echo h($titleText); ?>" loading="lazy">
      </div>
      <div class="meta">
        <div class="title"><?php echo h($titleText); ?></div>
        <div class="sub">
          <?php if ($cityText !== ''): ?><span><?php echo h($cityText); ?></span><span class="dot">•</span><?php endif; ?>
          <span><?php echo h(is_en() ? 'Status:' : 'وضعیت:'); ?> <?php echo h($status); ?></span>
        </div>

        <div style="margin-top:10px;">
          <a class="tag" href="<?php echo h(append_lang_to_url(BASE_URL . '/edit_my_listing.php?id=' . (int)$it['id'])); ?>">
            <?php echo h(is_en() ? 'Edit' : 'ویرایش'); ?>
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!count($items)): ?>
    <div class="empty"><?php echo h(is_en() ? 'You have not submitted any listings yet.' : 'شما هنوز آگهی‌ای ثبت نکرده‌اید.'); ?></div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>