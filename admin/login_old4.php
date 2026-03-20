<?php
require_once __DIR__ . '/../inc/config.php';

/*
if (session_status() === PHP_SESSION_NONE) {
    $path = (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL : '/';
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path'     => $path,
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    session_start();
}
*/

require_once __DIR__ . '/../inc/functions.php';

if (is_admin()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$err = '';

/*
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = isset($_POST['u']) ? trim($_POST['u']) : '';
    $p = isset($_POST['p']) ? (string)$_POST['p'] : '';

    
    if (admin_login($u, $p)) {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    } else {
        $err = 'نام کاربری یا رمز اشتباه است.';
    }
    
 
} */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = isset($_POST['u']) ? trim($_POST['u']) : '';
    $p = isset($_POST['p']) ? (string)$_POST['p'] : '';

    if (admin_login($u, $p)) {
        session_write_close();
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