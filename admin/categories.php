<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_admin();
require_once __DIR__ . '/../inc/header.php';

$pdo = db();
$msg = '';
$err = '';

$name = '';
$slug = '';
$sort = 0;

/* بررسی وجود ستون sort_order برای جلوگیری از خطا */
$has_sort_order = db_has_column($pdo, 'categories', 'sort_order');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = isset($_POST['name']) ? trim($_POST['name']) : '';
  $slug = isset($_POST['slug']) ? trim($_POST['slug']) : '';
  $sort = isset($_POST['sort']) ? (int)$_POST['sort'] : 0;

  if ($name === '') {
    $err = 'نام دسته‌بندی را وارد کنید.';
  } elseif ($slug === '') {
    $err = 'نام URL یا slug را وارد کنید.';
  } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    $err = 'Slug فقط باید شامل حروف انگلیسی کوچک، عدد و خط تیره باشد.';
  } else {
    $st = $pdo->prepare("SELECT id FROM categories WHERE slug=? LIMIT 1");
    $st->execute(array($slug));
    $exists = $st->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
      $err = 'این slug قبلاً استفاده شده است.';
    } else {
      try {
        if ($has_sort_order) {
          $stmt = $pdo->prepare("
            INSERT INTO categories(name,slug,sort_order,created_at)
            VALUES(?,?,?,?)
          ");
          $stmt->execute(array($name, $slug, $sort, now()));
        } else {
          $stmt = $pdo->prepare("
            INSERT INTO categories(name,slug,created_at)
            VALUES(?,?,?)
          ");
          $stmt->execute(array($name, $slug, now()));
        }

        $msg = 'دسته‌بندی اضافه شد.';
        $name = '';
        $slug = '';
        $sort = 0;
      } catch (Exception $e) {
        $err = 'خطا در ذخیره دسته‌بندی.';
      }
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

<h1 class="h1">مدیریت دسته‌بندی‌ها</h1>

<?php if ($msg): ?>
  <div class="success"><?php echo h($msg); ?></div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="error"><?php echo h($err); ?></div>
<?php endif; ?>

<form class="form" method="post">
  <label>نام دسته‌بندی (فارسی)</label>
  <input name="name" value="<?php echo h($name); ?>" required>

  <label>نام URL / Slug (انگلیسی)</label>
  <input
    name="slug"
    value="<?php echo h($slug); ?>"
    dir="ltr"
    placeholder="example: legal-services"
    required
  >
  <div class="muted" style="margin-top:6px;">
    فقط حروف انگلیسی کوچک، عدد و خط تیره - مجاز است.
  </div>

  <?php if ($has_sort_order): ?>
    <label>Sort Order</label>
    <input name="sort" type="number" value="<?php echo (int)$sort; ?>">
  <?php endif; ?>

  <button class="btn" type="submit">Add</button>
</form>

<table class="table">
  <tr>
    <th>ID</th>
    <th>نام</th>
    <th>Slug</th>
    <?php if ($has_sort_order): ?><th>Sort</th><?php endif; ?>
    <th>Action</th>
  </tr>

  <?php foreach ($cats as $c): ?>
    <tr>
      <td><?php echo (int)$c['id']; ?></td>
      <td><?php echo h($c['name']); ?></td>
      <td dir="ltr"><?php echo h($c['slug']); ?></td>
      <?php if ($has_sort_order): ?>
        <td><?php echo isset($c['sort_order']) ? (int)$c['sort_order'] : 0; ?></td>
      <?php endif; ?>
      <td>
        <a href="?del=<?php echo (int)$c['id']; ?>" onclick="return confirm('حذف شود؟')">Delete</a>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>