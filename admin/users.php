<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_admin();

$pdo = db();
$msg = '';
$err = '';

/* -----------------------------
   حذف کاربر
   قبل از هر خروجی HTML
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
  $uid = (int)$_POST['delete_user_id'];

  if ($uid > 0) {
    try {
      $pdo->beginTransaction();

      $st = $pdo->prepare("UPDATE listings SET user_id=NULL WHERE user_id=?");
      $st->execute(array($uid));

      $st2 = $pdo->prepare("DELETE FROM users WHERE id=?");
      $st2->execute(array($uid));

      $pdo->commit();

      header('Location: ' . BASE_URL . '/admin/users.php?deleted=1');
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      header('Location: ' . BASE_URL . '/admin/users.php?delete_error=1');
      exit;
    }
  } else {
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
  }
}

/* -----------------------------
   چک وجود جدول users
------------------------------*/
try {
  $pdo->query("SELECT id,email,created_at FROM users LIMIT 1");
} catch (Throwable $e) {
  require_once __DIR__ . '/../inc/header.php';
  echo '<div class="error">جدول users وجود ندارد یا دیتابیس کامل نیست.</div>';
  echo '<div class="muted" dir="ltr" style="unicode-bidi:plaintext;">' . h($e->getMessage()) . '</div>';
  require_once __DIR__ . '/../inc/footer.php';
  exit;
}

/* -----------------------------
   تشخیص اینکه ستون is_active داریم یا نه
------------------------------*/
$hasIsActive = false;
try {
  $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($cols as $c) {
    if (isset($c['name']) && $c['name'] === 'is_active') {
      $hasIsActive = true;
      break;
    }
  }
} catch (Throwable $e) {
  $hasIsActive = false;
}

/* -----------------------------
   لیست کاربران
------------------------------*/
try {
  if ($hasIsActive) {
    $sql = "
      SELECT
        u.id, u.email, u.created_at,
        u.is_active,
        (SELECT COUNT(*) FROM listings l WHERE l.user_id=u.id) AS listings_count
      FROM users u
      ORDER BY u.id DESC
      LIMIT 200
    ";
  } else {
    $sql = "
      SELECT
        u.id, u.email, u.created_at,
        1 AS is_active,
        (SELECT COUNT(*) FROM listings l WHERE l.user_id=u.id) AS listings_count
      FROM users u
      ORDER BY u.id DESC
      LIMIT 200
    ";
  }

  $users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $users = array();
  $err = 'خطا در خواندن کاربران: ' . $e->getMessage();
}

require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="h1">Users</h1>

<?php if (!empty($_GET['deleted'])): ?>
  <div class="success">کاربر حذف شد.</div>
<?php endif; ?>

<?php if (!empty($_GET['delete_error'])): ?>
  <div class="error">حذف کاربر انجام نشد.</div>
<?php endif; ?>

<?php if ($msg): ?>
  <div class="success"><?php echo h($msg); ?></div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="error"><?php echo h($err); ?></div>
<?php endif; ?>

<div class="muted" style="margin:8px 0;">
  تعداد کاربران: <strong><?php echo (int)count($users); ?></strong>
</div>

<?php if (!$hasIsActive): ?>
  <div class="muted" style="margin:8px 0;">
    نکته: ستون <span dir="ltr" style="unicode-bidi:plaintext;">users.is_active</span> در دیتابیس وجود ندارد؛ Active فعلاً همیشه Yes نمایش داده می‌شود.
  </div>
<?php endif; ?>

<table class="table">
  <tr>
    <th>ID</th>
    <th>Email</th>
    <th>Created</th>
    <th>Active</th>
    <th>Listings</th>
    <th>Actions</th>
  </tr>

  <?php foreach($users as $u): ?>
    <tr>
      <td><?php echo (int)$u['id']; ?></td>
      <td dir="ltr" style="unicode-bidi:plaintext;"><?php echo h($u['email']); ?></td>
      <td><?php echo h($u['created_at']); ?></td>
      <td><?php echo ((int)$u['is_active'] === 1) ? 'Yes' : 'No'; ?></td>
      <td><?php echo (int)$u['listings_count']; ?></td>
      <td style="white-space:nowrap;">
        <a class="btn" href="<?php echo BASE_URL; ?>/admin/edit_user.php?id=<?php echo (int)$u['id']; ?>">Edit</a>

        <form method="post" style="display:inline-block;margin:0"
              onsubmit="return confirm('کاربر حذف شود؟ آگهی‌هایش حذف نمی‌شوند ولی از پروفایل جدا می‌شوند.');">
          <input type="hidden" name="delete_user_id" value="<?php echo (int)$u['id']; ?>">
          <button class="btn" type="submit">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>

  <?php if (!count($users)): ?>
    <tr><td colspan="6">کاربری وجود ندارد.</td></tr>
  <?php endif; ?>
</table>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>