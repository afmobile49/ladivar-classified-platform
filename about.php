<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

$page_title = t('about') . ' | ' . t('site_name');
$page_desc  = is_en() ? 'About us and introduction to the classifieds website.' : 'درباره ما و معرفی سایت نیازمندی‌ها';

require_once __DIR__ . '/inc/header.php';
?>
<h1 class="h1"><?php echo h(t('about')); ?></h1>
<div class="body"><?php echo nl2br(h(get_setting_lang('about_text'))); ?></div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>