<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_admin();
require_once __DIR__ . '/../inc/header.php';

$pdo = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$st = $pdo->prepare("SELECT * FROM users WHERE id=?");
$st->execute([$id]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  echo '<div class="empty">کاربر پیدا نشد.</div>';
  require_once __DIR__ . '/../inc/footer.php';
  exit;
}

$msg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $is_active = !empty($_POST['is_active']) ? 1 : 0;

  if ($email === '' || strpos($email, '@') === false) {
    $errors[] = 'ایمیل معتبر نیست.';
  }

  // تغییر پسورد اختیاری
  $new_pass = trim($_POST['new_password'] ?? '');
  $updatePass = ($new_pass !== '');

  if ($updatePass && mb_strlen($new_pass, 'UTF-8') < 6) {
    $errors[] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
  }

  if (!count($errors)) {
    try {
      if ($updatePass) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET email=?, is_active=?, password_hash=? WHERE id=?");
        $upd->execute([$email, $is_active, $hash, $id]);
      } else {
        $upd = $pdo->prepare("UPDATE users SET email=?, is_active=? WHERE id=?");
        $upd->execute([$email, $is_active, $id]);
      }

      $msg = 'ذخیره شد.';
      $st->execute([$id]);
      $user = $st->fetch(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
      $errors[] = 'خطا در ذخیره (احتمالاً این ایمیل قبلاً ثبت شده).';
    }
  }
}
?>

<h1 class="h1">Edit User #<?php echo (int)$user['id']; ?></h1>

<?php if ($msg): ?><div class="success"><?php echo h($msg); ?></div><?php endif; ?>
<?php if (count($errors)): ?>
  <div class="error"><?php foreach($errors as $e) echo '<div>'.h($e).'</div>'; ?></div>
<?php endif; ?>

<form class="form" method="post">
  <label>Email</label>
  <input type="email" name="email" value="<?php echo h($user['email']); ?>" required>

  <label>
    <input type="checkbox" name="is_active" value="1" <?php echo ((int)$user['is_active']===1)?'checked':''; ?>>
    Active
  </label>

  <label>New Password (optional)</label>
  <input type="password" name="new_password" placeholder="اگر نمی‌خواهید تغییر کند خالی بگذارید">

  <button class="btn" type="submit">Save</button>
  <a class="btn" href="<?php echo BASE_URL; ?>/admin/users.php">Back</a>
</form>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>