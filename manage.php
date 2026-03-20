<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

$page_title = is_en() ? ('Manage Listing | ' . t('site_name')) : ('مدیریت آگهی | ' . t('site_name'));
$page_desc  = is_en() ? 'Manage your listing using the management code.' : 'آگهی خود را با کد مدیریت ویرایش کنید.';

$error = '';
$item = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['manage_code'] ?? '');

    if ($code === '') {
        $error = is_en() ? 'Please enter the management code.' : 'لطفاً کد مدیریت را وارد کنید.';
    } else {
        if (function_exists('find_listing_by_manage_code')) {
            $item = find_listing_by_manage_code($code);
        }

        if (!$item) {
            $error = is_en() ? 'No listing was found with this code.' : 'آگهی‌ای با این کد پیدا نشد.';
        }
    }
}

require_once __DIR__ . '/inc/header.php';
?>

<h1 class="h1"><?php echo h(is_en() ? 'Manage Listing' : 'مدیریت آگهی'); ?></h1>

<?php if ($error !== ''): ?>
  <div class="error"><?php echo h($error); ?></div>
<?php endif; ?>

<?php if (!$item): ?>
  <form method="post" class="formCard">
    <div class="field">
      <label><?php echo h(is_en() ? 'Management Code' : 'کد مدیریت'); ?></label>
      <input type="text" name="manage_code" value="<?php echo h($_POST['manage_code'] ?? ''); ?>" required>
    </div>
    <button class="btn" type="submit"><?php echo h(is_en() ? 'Open Listing' : 'باز کردن آگهی'); ?></button>
  </form>
<?php else: ?>
  <div class="success">
    <?php echo h(is_en() ? 'Listing found. You can edit it here:' : 'آگهی پیدا شد. می‌توانید از اینجا آن را ویرایش کنید:'); ?>
    <a href="<?php echo h(append_lang_to_url(BASE_URL . '/edit_my_listing.php?id=' . (int)$item['id'] . '&code=' . urlencode($_POST['manage_code']))); ?>">
      <?php echo h(is_en() ? 'Edit Listing' : 'ویرایش آگهی'); ?>
    </a>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/inc/footer.php'; ?>