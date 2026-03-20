<?php
// admin/login.php

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';

/* ✅ Session cookie path fix:
   اگر سایت داخل /ladivar است باید path همین باشد تا dashboard هم سشن را ببیند */
if (session_status() === PHP_SESSION_NONE) {
  $path = (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL : '/';
  // اگر BASE_URL مثل /ladivar است، همین خوب است. اگر خالی بود، / می‌شود.
  session_set_cookie_params(array(
    'lifetime' => 0,
    'path'     => $path,
    'httponly' => true,
    'samesite' => 'Lax',
    // 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
  ));
  session_start();
}

// اگر قبلاً لاگین است
if (function_exists('is_admin') && is_admin()) {
  header('Location: ' . BASE_URL . '/admin/dashboard.php');
  exit;
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = isset($_POST['u']) ? trim((string)$_POST['u']) : '';
  $p = isset($_POST['p']) ? trim((string)$_POST['p']) : '';

  if ($u === ADMIN_USER && $p === ADMIN_PASS) {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = 1; // ✅ دقیقا همین کلید باید ست شود

    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
  } else {
    $err = 'نام کاربری یا رمز اشتباه است.';
  }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login</title>
  <link rel="stylesheet" href="<?php echo h(BASE_URL); ?>/assets/style.css">
</head>
<body>
  <div class="adminbox">
    <h1 class="h1">Admin Login</h1>

    <?php if ($err): ?>
      <div class="error"><?php echo h($err); ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <label>Username</label>
      <input name="u" required autocomplete="username">

      <label>Password</label>
      <input name="p" type="password" required autocomplete="current-password">

      <button class="btn" type="submit">Login</button>
    </form>
  </div>
</body>
</html>