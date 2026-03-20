<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';

if (is_admin()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = isset($_POST['u']) ? trim($_POST['u']) : '';
    $p = isset($_POST['p']) ? (string)$_POST['p'] : '';

    if (admin_login($u, $p)) {
        
    /*    
    echo '<pre>';
    echo "LOGIN OK\n";
    echo "session_id: " . session_id() . "\n";
    print_r($_SESSION);
    echo '</pre>';
      */  
        
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
  <meta name="robots" content="noindex,nofollow">
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