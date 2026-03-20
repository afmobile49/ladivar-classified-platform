<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

//if (session_status() === PHP_SESSION_NONE) session_start();
//if (function_exists('db_init')) db_init();

/* فقط ادمین */
require_admin();

$page_title = 'Settings | ' . SITE_NAME;
$page_desc  = 'تنظیمات سایت';

require_once __DIR__ . '/inc/header.php';

/* اگر set_setting نداریم، همینجا تعریفش کنیم */
if (!function_exists('set_setting')) {
  function set_setting(string $k, string $v): void {
    $pdo = db();
    // SQLite: INSERT OR REPLACE بهترین گزینه است
    $st = $pdo->prepare("INSERT OR REPLACE INTO settings(k,v) VALUES(?,?)");
    $st->execute([$k, $v]);
  }
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $phone = trim((string)($_POST['admin_phone'] ?? ''));
  $email = trim((string)($_POST['admin_email'] ?? ''));
  $footer_help = trim((string)($_POST['footer_help'] ?? ''));

  set_setting('admin_phone', $phone);
  set_setting('admin_email', $email);
  set_setting('footer_help', $footer_help);

  $msg = 'تنظیمات ذخیره شد.';
}

$phone = get_setting('admin_phone');
$email = get_setting('admin_email');
$footer_help = get_setting('footer_help');
?>

<h1 class="h1">Settings</h1>

<?php if ($msg): ?>
  <div class="success"><?php echo h($msg); ?></div>
<?php endif; ?>

<form class="form" method="post">
  <label>Phone</label>
  <input type="text" name="admin_phone" value="<?php echo h($phone); ?>">

  <label>Email</label>
  <input type="text" name="admin_email" value="<?php echo h($email); ?>">

  <label>Footer Help Text</label>
  <textarea name="footer_help" rows="4"><?php echo h($footer_help); ?></textarea>

  <button class="btn" type="submit">ذخیره تنظیمات</button>
</form>

<?php require_once __DIR__ . '/inc/footer.php'; ?>