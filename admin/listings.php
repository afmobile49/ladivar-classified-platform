<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_admin();
require_once __DIR__ . '/../inc/header.php';

$pdo = db();
$msg = '';

if (isset($_GET['approve'])) {
  $id = (int)$_GET['approve'];
  $pdo->prepare("UPDATE listings SET status='approved', approved_at=? WHERE id=?")->execute(array(now(), $id));
  $msg = 'تایید شد.';
}
if (isset($_GET['reject'])) {
  $id = (int)$_GET['reject'];
  $pdo->prepare("UPDATE listings SET status='rejected' WHERE id=?")->execute(array($id));
  $msg = 'رد شد.';
}
if (isset($_GET['del'])) {
  $id = (int)$_GET['del'];
  $pdo->prepare("DELETE FROM listings WHERE id=?")->execute(array($id));
  $msg = 'حذف شد.';
}

/*
$pending = $pdo->query("
  SELECT l.*, c.name AS cat_name
  FROM listings l JOIN categories c ON c.id=l.category_id
  WHERE l.status='pending' ORDER BY l.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
*/

$pending = $pdo->query("
  SELECT
  l.*,
  c.name AS cat_name,
  COALESCE(l.list_view_count, 0) AS list_view_count,
  COALESCE(l.detail_view_count, 0) AS detail_view_count
  FROM listings l JOIN categories c ON c.id=l.category_id
  WHERE l.status='pending' ORDER BY l.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<h1 class="h1">Approve Listings</h1>
<?php if($msg) echo '<div class="success">'.h($msg).'</div>'; ?>

<table class="table">
<tr>
  <th>ID</th>
  <th>عنوان</th>
  <th>دسته</th>
  <th>تاریخ</th>
  <th>نمایش لیست</th>
  <th>ورود به آگهی</th>
  <th>Action</th>
</tr>
  <?php foreach($pending as $it): ?>
    <tr>
      <td><?php echo (int)$it['id']; ?></td>
      <td><?php echo h($it['title']); ?></td>
      <td><?php echo h($it['cat_name']); ?></td>
      <td><?php echo h($it['created_at']); ?></td>
      
      <td><?php echo (int)$it['list_view_count']; ?></td>
      <td><?php echo (int)$it['detail_view_count']; ?></td>        
       
      <td>
        <a href="?approve=<?php echo (int)$it['id']; ?>">Approve</a> |
        <a href="?reject=<?php echo (int)$it['id']; ?>">Reject</a> |
        <a href="?del=<?php echo (int)$it['id']; ?>" onclick="return confirm('حذف شود؟')">Delete</a>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php if(!count($pending)) echo '<div class="empty">موردی برای تایید وجود ندارد.</div>'; ?>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>