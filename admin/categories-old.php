<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_admin();
require_once __DIR__ . '/../inc/header.php';

$pdo = db();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = isset($_POST['name']) ? trim($_POST['name']) : '';
  $sort = isset($_POST['sort']) ? (int)$_POST['sort'] : 0;
  if ($name === '') $err = 'نام دسته‌بندی را وارد کنید.';
  if (!$err) {
    $slug = slugify($name);
    $stmt = $pdo->prepare("INSERT INTO categories(name,slug,sort_order,created_at) VALUES(?,?,?,?)");
    try {
      $stmt->execute(array($name, $slug, $sort, now()));
      $msg = 'دسته‌بندی اضافه شد.';
    } catch(Exception $e) {
      $err = 'این دسته‌بندی/اسلاگ تکراری است.';
    }
  }
}

if (isset($_GET['del'])) {
  $id = (int)$_GET['del'];
  $pdo->prepare("DELETE FROM categories WHERE id=?")->execute(array($id));
  $msg = 'حذف شد.';
}

$cats = categories_all();
?>

<h1 class="h1">Manage Categories</h1>
<?php if($msg) echo '<div class="success">'.h($msg).'</div>'; ?>
<?php if($err) echo '<div class="error">'.h($err).'</div>'; ?>

<form class="form" method="post">
  <label>نام دسته‌بندی</label>
  <input name="name" required>
  <label>Sort Order</label>
  <input name="sort" type="number" value="0">
  <button class="btn" type="submit">Add</button>
</form>

<table class="table">
  <tr><th>ID</th><th>نام</th><th>Slug</th><th>Sort</th><th>Action</th></tr>
  <?php foreach($cats as $c): ?>
    <tr>
      <td><?php echo (int)$c['id']; ?></td>
      <td><?php echo h($c['name']); ?></td>
      <td><?php echo h($c['slug']); ?></td>
      <td><?php echo (int)$c['sort_order']; ?></td>
      <td><a href="?del=<?php echo (int)$c['id']; ?>" onclick="return confirm('حذف شود؟')">Delete</a></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>