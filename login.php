<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

if (function_exists('is_user_logged_in') && is_user_logged_in()) {
    header('Location: ' . append_lang_to_url(BASE_URL . '/my_listings.php'));
    exit;
}

$page_title = t('login') . ' | ' . t('site_name');
$page_desc  = is_en() ? 'Login to manage your listings.' : 'برای مدیریت آگهی‌های خود وارد شوید.';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = is_en() ? 'Please enter email and password.' : 'لطفاً ایمیل و رمز عبور را وارد کنید.';
    } else {
        //if (function_exists('login_user') && login_user($email, $password)) {
        if (function_exists('user_login') && user_login($email, $password)) {
            header('Location: ' . append_lang_to_url(BASE_URL . '/my_listings.php'));
            exit;
        } else {
            $error = is_en() ? 'Login failed. Email or password is incorrect.' : 'ورود ناموفق بود. ایمیل یا رمز عبور اشتباه است.';
        }
    }
}

require_once __DIR__ . '/inc/header.php';
?>

<h1 class="h1"><?php echo h(t('login')); ?></h1>

<?php if ($error !== ''): ?>
  <div class="error"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="post" class="formCard">
  <div class="field">
    <label><?php echo h(is_en() ? 'Email' : 'ایمیل'); ?></label>
    <input type="email" name="email" value="<?php echo h($_POST['email'] ?? ''); ?>" required>
  </div>

  <div class="field">
    <label><?php echo h(is_en() ? 'Password' : 'رمز عبور'); ?></label>
    <input type="password" name="password" required>
  </div>

  <button class="btn" type="submit"><?php echo h(t('login')); ?></button>
</form>

<div class="muted" style="margin-top:14px;">
  <?php if (is_en()): ?>
    Don't have an account?
    <a href="<?php echo h(append_lang_to_url(BASE_URL . '/register.php')); ?>">Register</a>
  <?php else: ?>
    حساب ندارید؟
    <a href="<?php echo h(append_lang_to_url(BASE_URL . '/register.php')); ?>">ثبت‌نام</a>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>