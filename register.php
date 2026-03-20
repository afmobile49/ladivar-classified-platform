<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

if (function_exists('is_user_logged_in') && is_user_logged_in()) {
    header('Location: ' . append_lang_to_url(BASE_URL . '/my_listings.php'));
    exit;
}

$page_title = t('register') . ' | ' . t('site_name');
$page_desc  = is_en() ? 'Create an account to manage your listings.' : 'برای مدیریت آگهی‌های خود حساب ایجاد کنید.';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        $error = is_en() ? 'Please fill in all required fields.' : 'لطفاً همه فیلدهای لازم را پر کنید.';
    } else {
        /*
        if (function_exists('register_user')) {
            //$result = register_user($name, $email, $password);
            $result = user_register($email, $password);

            if ($result === true) {
                $success = is_en() ? 'Your account was created successfully. You can now log in.' : 'حساب شما با موفقیت ساخته شد. اکنون می‌توانید وارد شوید.';
            } else {
                $error = is_string($result) ? $result : (is_en() ? 'Registration failed.' : 'ثبت‌نام ناموفق بود.');
            }
        } else {
            $error = is_en() ? 'Registration system is not available.' : 'سیستم ثبت‌نام در دسترس نیست.';
        }
        */
        
        if (function_exists('user_register')) {
            $result = user_register($email, $password);
        
            if (is_array($result) && !empty($result[0])) {
                $success = is_en()
                    ? 'Your account was created successfully. You can now log in.'
                    : 'حساب شما با موفقیت ساخته شد. اکنون می‌توانید وارد شوید.';
            } else {
                $msg = is_array($result) && !empty($result[1])
                    ? $result[1]
                    : (is_en() ? 'Registration failed.' : 'ثبت‌نام ناموفق بود.');
                $error = $msg;
            }
        } else {
            $error = is_en()
                ? 'Registration system is not available.'
                : 'سیستم ثبت‌نام در دسترس نیست.';
        }        
        
    }
}

require_once __DIR__ . '/inc/header.php';
?>

<h1 class="h1"><?php echo h(t('register')); ?></h1>

<?php if ($error !== ''): ?>
  <div class="error"><?php echo h($error); ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
  <div class="success"><?php echo h($success); ?></div>
<?php endif; ?>

<form method="post" class="formCard">
  <div class="field">
    <label><?php echo h(is_en() ? 'Full name' : 'نام'); ?></label>
    <input type="text" name="name" value="<?php echo h($_POST['name'] ?? ''); ?>" required>
  </div>

  <div class="field">
    <label><?php echo h(is_en() ? 'Email' : 'ایمیل'); ?></label>
    <input type="email" name="email" value="<?php echo h($_POST['email'] ?? ''); ?>" required>
  </div>

  <div class="field">
    <label><?php echo h(is_en() ? 'Password' : 'رمز عبور'); ?></label>
    <input type="password" name="password" required>
  </div>

  <button class="btn" type="submit"><?php echo h(t('register')); ?></button>
</form>

<div class="muted" style="margin-top:14px;">
  <?php if (is_en()): ?>
    Already have an account?
    <a href="<?php echo h(append_lang_to_url(BASE_URL . '/login.php')); ?>">Login</a>
  <?php else: ?>
    قبلاً حساب ساخته‌اید؟
    <a href="<?php echo h(append_lang_to_url(BASE_URL . '/login.php')); ?>">ورود</a>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>