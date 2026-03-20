<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

$page_title = t('help') . ' | ' . t('site_name');
$page_desc  = is_en() ? 'Guide to using the website.' : 'راهنمای استفاده از سایت';

require_once __DIR__ . '/inc/header.php';
?>

<h1 class="h1"><?php echo h(t('help')); ?></h1>

<div class="body">
  <?php
  $txt = function_exists('get_setting_lang') ? get_setting_lang('help_text') : '';
  echo nl2br(h((string)$txt));
  ?>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>