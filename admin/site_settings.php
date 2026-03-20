<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';

//if (session_status() === PHP_SESSION_NONE) session_start();
//if (function_exists('db_init')) db_init();

require_admin();

$page_title = 'Site Settings | ' . SITE_NAME;
$page_desc  = 'تنظیمات کلی سایت';

require_once __DIR__ . '/../inc/header.php';

/* اگر set_setting نداریم، همینجا تعریفش کنیم */
if (!function_exists('set_setting')) {
  function set_setting(string $k, string $v): void {
    $pdo = db();
    $st = $pdo->prepare("INSERT OR REPLACE INTO settings(k,v) VALUES(?,?)");
    $st->execute([$k, $v]);
  }
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  set_setting('admin_phone', trim((string)($_POST['admin_phone'] ?? '')));
  set_setting('admin_email', trim((string)($_POST['admin_email'] ?? '')));
  set_setting('footer_help', trim((string)($_POST['footer_help'] ?? '')));
  set_setting('help_text', trim((string)($_POST['help_text'] ?? '')));
  set_setting('about_text', trim((string)($_POST['about_text'] ?? '')));
  $msg = 'ذخیره شد.';
}
?>

<h1 class="h1">Site Settings</h1>
<?php if ($msg): ?>
  <div class="success"><?php echo h($msg); ?></div>
<?php endif; ?>

<form class="form" method="post">
  <label>Admin Phone</label>
  <input name="admin_phone" value="<?php echo h(get_setting('admin_phone')); ?>">

  <label>Admin Email</label>
  <input name="admin_email" value="<?php echo h(get_setting('admin_email')); ?>">

  <label>Footer Note</label>
  <input name="footer_help" value="<?php echo h(get_setting('footer_help')); ?>">

  <label>Help Text</label>
  <textarea name="help_text" rows="6"><?php echo h(get_setting('help_text')); ?></textarea>

  <label>About Text</label>
  <textarea name="about_text" rows="6"><?php echo h(get_setting('about_text')); ?></textarea>

  <button class="btn" type="submit">Save</button>
</form>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>