<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_admin();
require_once __DIR__ . '/../inc/header.php';

$pdo = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

function edit_ad_has_column(PDO $pdo, string $table, string $col): bool {
  return db_has_column($pdo, $table, $col);
}

$hasTitleEn     = edit_ad_has_column($pdo, 'side_ads', 'title_en');
$hasHtmlEn      = edit_ad_has_column($pdo, 'side_ads', 'html_en');
$hasImagePath   = edit_ad_has_column($pdo, 'side_ads', 'image_path');
$hasImagePathEn = edit_ad_has_column($pdo, 'side_ads', 'image_path_en');
$hasImageScope  = edit_ad_has_column($pdo, 'side_ads', 'image_scope');

function normalize_side_ad_scope($scope) {
  $scope = trim((string)$scope);
  if (!in_array($scope, array('both', 'fa', 'en'), true)) {
    return 'both';
  }
  return $scope;
}

function save_uploaded_side_ad_image($f, $uploadDir) {
  $tmp = isset($f['tmp_name']) ? $f['tmp_name'] : '';
  $info = @getimagesize($tmp);
  if (!$info) return null;

  $mime = isset($info['mime']) ? $info['mime'] : 'image/jpeg';
  $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');

  $rand = function_exists('random_bytes')
    ? bin2hex(random_bytes(6))
    : substr(md5(uniqid('', true)), 0, 12);

  $name = 'ad_' . date('Ymd_His') . '_' . $rand . '.' . $ext;
  $dest = rtrim($uploadDir, '/') . '/' . $name;

  if (!@move_uploaded_file($tmp, $dest)) return null;
  return $name;
}

$st = $pdo->prepare("SELECT * FROM side_ads WHERE id=? LIMIT 1");
$st->execute(array($id));
$ad = $st->fetch(PDO::FETCH_ASSOC);

if (!$ad) {
  echo '<div class="empty">تبلیغ پیدا نشد.</div>';
  require_once __DIR__ . '/../inc/footer.php';
  exit;
}

$msg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $position   = trim((string)($_POST['position'] ?? 'right'));
  $title      = trim((string)($_POST['title'] ?? ''));
  $title_en   = trim((string)($_POST['title_en'] ?? ''));
  $html       = trim((string)($_POST['html'] ?? ''));
  $html_en    = trim((string)($_POST['html_en'] ?? ''));
  $sort_order = (int)($_POST['sort_order'] ?? 0);
  $is_active  = !empty($_POST['is_active']) ? 1 : 0;
  $image_scope = normalize_side_ad_scope($_POST['image_scope'] ?? 'both');

  if (!in_array($position, array('left', 'right'), true)) {
    $position = 'right';
  }

  $uploadDir = __DIR__ . '/../uploads/ads';
  if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
  }

  $newImageFa = '';
  $newImageEn = '';

  $deleteImageFa = !empty($_POST['delete_image_fa']);
  $deleteImageEn = !empty($_POST['delete_image_en']);

  $hasImageFaUpload = !empty($_FILES['image_fa']) && ($_FILES['image_fa']['name'] ?? '') !== '';
  $hasImageEnUpload = !empty($_FILES['image_en']) && ($_FILES['image_en']['name'] ?? '') !== '';

  if ($hasImageFaUpload) {
    $v = validate_image_upload($_FILES['image_fa']);
    if ($v) $errors[] = 'تصویر فارسی/عمومی: ' . $v;
  }

  if ($hasImageEnUpload) {
    $v = validate_image_upload($_FILES['image_en']);
    if ($v) $errors[] = 'تصویر انگلیسی: ' . $v;
  }

  if (!count($errors)) {
    try {
      if ($hasImageFaUpload) {
        $saved = save_uploaded_side_ad_image($_FILES['image_fa'], $uploadDir);
        if ($saved) $newImageFa = $saved;
      }

      if ($hasImageEnUpload) {
        $saved = save_uploaded_side_ad_image($_FILES['image_en'], $uploadDir);
        if ($saved) $newImageEn = $saved;
      }

      $pdo->beginTransaction();

      $fields = array(
        'position = ?',
        'title = ?',
        'html = ?',
        'is_active = ?',
        'sort_order = ?'
      );
      $values = array(
        $position,
        $title,
        $html,
        $is_active,
        $sort_order
      );

      if ($hasTitleEn) {
        $fields[] = 'title_en = ?';
        $values[] = $title_en;
      }

      if ($hasHtmlEn) {
        $fields[] = 'html_en = ?';
        $values[] = $html_en;
      }

      if ($hasImageScope) {
        $fields[] = 'image_scope = ?';
        $values[] = $image_scope;
      }

      if ($hasImagePath) {
        if ($deleteImageFa) {
          $fields[] = 'image_path = ?';
          $values[] = '';
        } elseif ($newImageFa !== '') {
          $fields[] = 'image_path = ?';
          $values[] = $newImageFa;
        }
      }

      if ($hasImagePathEn) {
        if ($deleteImageEn) {
          $fields[] = 'image_path_en = ?';
          $values[] = '';
        } elseif ($newImageEn !== '') {
          $fields[] = 'image_path_en = ?';
          $values[] = $newImageEn;
        }
      }

      $values[] = $id;

      $sql = "UPDATE side_ads SET " . implode(', ', $fields) . " WHERE id = ?";
      $upd = $pdo->prepare($sql);
      $upd->execute($values);

      $pdo->commit();

      // حذف فایل‌های قدیمی بعد از commit
      if ($deleteImageFa && $hasImagePath && !empty($ad['image_path'])) {
        $old = __DIR__ . '/../uploads/ads/' . ltrim((string)$ad['image_path'], '/');
        if (is_file($old)) @unlink($old);
      }
      if ($deleteImageEn && $hasImagePathEn && !empty($ad['image_path_en'])) {
        $old = __DIR__ . '/../uploads/ads/' . ltrim((string)$ad['image_path_en'], '/');
        if (is_file($old)) @unlink($old);
      }

      if ($newImageFa !== '' && $hasImagePath && !empty($ad['image_path'])) {
        $old = __DIR__ . '/../uploads/ads/' . ltrim((string)$ad['image_path'], '/');
        if (is_file($old)) @unlink($old);
      }
      if ($newImageEn !== '' && $hasImagePathEn && !empty($ad['image_path_en'])) {
        $old = __DIR__ . '/../uploads/ads/' . ltrim((string)$ad['image_path_en'], '/');
        if (is_file($old)) @unlink($old);
      }

      $msg = 'تبلیغ با موفقیت ویرایش شد.';

      $st->execute(array($id));
      $ad = $st->fetch(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'ویرایش تبلیغ انجام نشد.';
    }
  }
}
?>

<h1 class="h1">ویرایش تبلیغ #<?php echo (int)$ad['id']; ?></h1>

<?php if ($msg): ?>
  <div class="success"><?php echo h($msg); ?></div>
<?php endif; ?>

<?php if (count($errors)): ?>
  <div class="error">
    <?php foreach ($errors as $e): ?>
      <div><?php echo h($e); ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form">
  <label>Position</label>
  <select name="position">
    <option value="right" <?php echo (($ad['position'] ?? '') === 'right') ? 'selected' : ''; ?>>Right</option>
    <option value="left" <?php echo (($ad['position'] ?? '') === 'left') ? 'selected' : ''; ?>>Left</option>
  </select>

  <label>Title</label>
  <input name="title" value="<?php echo h($ad['title'] ?? ''); ?>">

  <?php if ($hasTitleEn): ?>
    <label>English Title</label>
    <input name="title_en" value="<?php echo h($ad['title_en'] ?? ''); ?>">
  <?php endif; ?>

  <?php if ($hasImageScope): ?>
    <label>Image Scope</label>
    <select name="image_scope">
      <option value="both" <?php echo (($ad['image_scope'] ?? 'both') === 'both') ? 'selected' : ''; ?>>Both languages</option>
      <option value="fa" <?php echo (($ad['image_scope'] ?? '') === 'fa') ? 'selected' : ''; ?>>Persian only</option>
      <option value="en" <?php echo (($ad['image_scope'] ?? '') === 'en') ? 'selected' : ''; ?>>English only</option>
    </select>
  <?php endif; ?>

  <?php if ($hasImagePath): ?>
    <h2 class="h1" style="font-size:16px; margin-top:18px;">تصویر فارسی / عمومی</h2>

    <?php if (!empty($ad['image_path'])): ?>
      <div style="margin-bottom:10px;">
        <img src="<?php echo h(BASE_URL . '/uploads/ads/' . $ad['image_path']); ?>" style="max-width:180px;height:auto;border-radius:10px;">
      </div>
      <label>
        <input type="checkbox" name="delete_image_fa" value="1"> حذف تصویر فارسی / عمومی
      </label>
    <?php else: ?>
      <div class="muted">تصویر فارسی / عمومی ثبت نشده است.</div>
    <?php endif; ?>

    <label>آپلود تصویر فارسی / عمومی جدید</label>
    <input type="file" name="image_fa" accept="image/*">
  <?php endif; ?>

  <?php if ($hasImagePathEn): ?>
    <h2 class="h1" style="font-size:16px; margin-top:18px;">English Image</h2>

    <?php if (!empty($ad['image_path_en'])): ?>
      <div style="margin-bottom:10px;">
        <img src="<?php echo h(BASE_URL . '/uploads/ads/' . $ad['image_path_en']); ?>" style="max-width:180px;height:auto;border-radius:10px;">
      </div>
      <label>
        <input type="checkbox" name="delete_image_en" value="1"> Delete English image
      </label>
    <?php else: ?>
      <div class="muted">No English image uploaded.</div>
    <?php endif; ?>

    <label>Upload new English image</label>
    <input type="file" name="image_en" accept="image/*">
  <?php endif; ?>

  <label>HTML (اختیاری)</label>
  <textarea name="html" rows="6"><?php echo h($ad['html'] ?? ''); ?></textarea>

  <?php if ($hasHtmlEn): ?>
    <label>English HTML (optional)</label>
    <textarea name="html_en" rows="6"><?php echo h($ad['html_en'] ?? ''); ?></textarea>
  <?php endif; ?>

  <label>Sort</label>
  <input type="number" name="sort_order" value="<?php echo (int)($ad['sort_order'] ?? 0); ?>">

  <label>
    <input type="checkbox" name="is_active" value="1" <?php echo !empty($ad['is_active']) ? 'checked' : ''; ?>> Active
  </label>

  <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
    <button class="btn" type="submit">Save Changes</button>
    <a class="btn" href="<?php echo h(BASE_URL . '/admin/ads.php'); ?>">Back</a>
  </div>
</form>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>