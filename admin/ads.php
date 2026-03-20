<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_admin();
require_once __DIR__ . '/../inc/header.php';

$pdo = db();
$msg = '';
$errors = [];

function ads_has_column(PDO $pdo, string $table, string $col): bool {
  return db_has_column($pdo, $table, $col);
}

$hasTitleEn     = ads_has_column($pdo, 'side_ads', 'title_en');
$hasHtmlEn      = ads_has_column($pdo, 'side_ads', 'html_en');
$hasImagePath   = ads_has_column($pdo, 'side_ads', 'image_path');
$hasImagePathEn = ads_has_column($pdo, 'side_ads', 'image_path_en');
$hasImageScope  = ads_has_column($pdo, 'side_ads', 'image_scope');

function normalize_ad_image_scope($scope) {
  $scope = trim((string)$scope);
  if (!in_array($scope, array('both', 'fa', 'en'), true)) {
    return 'both';
  }
  return $scope;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $delete_id = (int)$_POST['delete_id'];

  if ($delete_id > 0) {
    $st = $pdo->prepare("SELECT * FROM side_ads WHERE id=?");
    $st->execute(array($delete_id));
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      try {
        $pdo->beginTransaction();

        $del = $pdo->prepare("DELETE FROM side_ads WHERE id=?");
        $del->execute(array($delete_id));

        $pdo->commit();

        if ($hasImagePath && !empty($row['image_path'])) {
          $full = __DIR__ . '/../uploads/ads/' . ltrim((string)$row['image_path'], '/');
          if (is_file($full)) @unlink($full);
        }

        if ($hasImagePathEn && !empty($row['image_path_en'])) {
          $full = __DIR__ . '/../uploads/ads/' . ltrim((string)$row['image_path_en'], '/');
          if (is_file($full)) @unlink($full);
        }

        $msg = 'تبلیغ حذف شد.';
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = 'حذف تبلیغ انجام نشد.';
      }
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
  $pos      = trim((string)($_POST['position'] ?? 'right'));
  $title    = trim((string)($_POST['title'] ?? ''));
  $title_en = trim((string)($_POST['title_en'] ?? ''));
  $html     = trim((string)($_POST['html'] ?? ''));
  $html_en  = trim((string)($_POST['html_en'] ?? ''));
  $sort     = (int)($_POST['sort_order'] ?? 0);
  $active   = !empty($_POST['is_active']) ? 1 : 0;
  $imageScope = normalize_ad_image_scope($_POST['image_scope'] ?? 'both');

  if (!in_array($pos, array('left', 'right'), true)) {
    $pos = 'right';
  }

  $uploadDir = __DIR__ . '/../uploads/ads';
  if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
  }

  $imagePathFa = '';
  $imagePathEn = '';

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
        $saved = save_uploaded_ad_image($_FILES['image_fa'], $uploadDir);
        if ($saved) {
          $imagePathFa = $saved;
        }
      }

      if ($hasImageEnUpload) {
        $saved = save_uploaded_ad_image($_FILES['image_en'], $uploadDir);
        if ($saved) {
          $imagePathEn = $saved;
        }
      }

      $fields = array('position', 'title', 'html', 'is_active', 'sort_order', 'created_at');
      $values = array($pos, $title, $html, $active, $sort, now());

      if ($hasTitleEn) {
        $fields[] = 'title_en';
        $values[] = $title_en;
      }

      if ($hasHtmlEn) {
        $fields[] = 'html_en';
        $values[] = $html_en;
      }

      if ($hasImagePath) {
        $fields[] = 'image_path';
        $values[] = $imagePathFa;
      }

      if ($hasImagePathEn) {
        $fields[] = 'image_path_en';
        $values[] = $imagePathEn;
      }

      if ($hasImageScope) {
        $fields[] = 'image_scope';
        $values[] = $imageScope;
      }

      $placeholders = implode(',', array_fill(0, count($fields), '?'));
      $sql = "INSERT INTO side_ads(" . implode(',', $fields) . ") VALUES(" . $placeholders . ")";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($values);

      $msg = 'تبلیغ جدید اضافه شد.';
    } catch (Throwable $e) {
      $errors[] = 'ذخیره تبلیغ انجام نشد.';
    }
  }
}

function save_uploaded_ad_image($f, $uploadDir) {
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

$ads = $pdo->query("SELECT * FROM side_ads ORDER BY sort_order ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="h1">Side Ads</h1>

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
    <option value="right">Right</option>
    <option value="left">Left</option>
  </select>

  <label>Title</label>
  <input name="title" placeholder="اختیاری">

  <?php if ($hasTitleEn): ?>
    <label>English Title</label>
    <input name="title_en" placeholder="Optional English title">
  <?php endif; ?>

  <?php if ($hasImagePath): ?>
    <label>تصویر فارسی / عمومی (اختیاری)</label>
    <input type="file" name="image_fa" accept="image/*">
    <div class="muted">اگر این تبلیغ برای فارسی یا هر دو زبان است، اینجا تصویر را آپلود کن.</div>
  <?php endif; ?>

  <?php if ($hasImagePathEn): ?>
    <label>English Image (optional)</label>
    <input type="file" name="image_en" accept="image/*">
    <div class="muted">If this ad should show a different image in English, upload it here.</div>
  <?php endif; ?>

  <?php if ($hasImageScope): ?>
    <label>Image Scope</label>
    <select name="image_scope">
      <option value="both">Both languages</option>
      <option value="fa">Persian only</option>
      <option value="en">English only</option>
    </select>
  <?php endif; ?>

  <label>HTML (اختیاری)</label>
  <textarea name="html" rows="5" placeholder="<a href='...'>متن/دکمه/لینک</a>"></textarea>

  <?php if ($hasHtmlEn): ?>
    <label>English HTML (optional)</label>
    <textarea name="html_en" rows="5" placeholder="<a href='...'>English text/button/link</a>"></textarea>
  <?php endif; ?>

  <label>Sort</label>
  <input type="number" name="sort_order" value="0">

  <label>
    <input type="checkbox" name="is_active" value="1" checked> Active
  </label>

  <button class="btn" type="submit">Add</button>
</form>

<table class="table" style="margin-top:20px;">
  <tr>
    <th>Action</th>
    <th>Preview</th>
    <th>Sort</th>
    <th>Active</th>
    <th>Image Scope</th>
    <th>FA Img</th>
    <th>EN Img</th>
    <th>Title</th>
    <th>Pos</th>
    <th>ID</th>
  </tr>

  <?php foreach ($ads as $ad): ?>
    <tr>

    <td style="white-space:nowrap;">
      <a class="btn" href="<?php echo h(BASE_URL . '/admin/edit_ad.php?id=' . (int)$ad['id']); ?>">Edit</a>
    
      <form method="post" onsubmit="return confirm('Delete this ad?');" style="display:inline-block;">
        <input type="hidden" name="delete_id" value="<?php echo (int)$ad['id']; ?>">
        <button class="btn" type="submit">Delete</button>
      </form>
    </td>


<td style="max-width:280px;">
  <?php
    $scope = trim((string)($ad['image_scope'] ?? 'both'));
    if (!in_array($scope, array('both','fa','en'), true)) {
      $scope = 'both';
    }

    $previewImg = '';
    if ($scope === 'en') {
      $previewImg = trim((string)($ad['image_path_en'] ?? ''));
    } elseif ($scope === 'fa') {
      $previewImg = trim((string)($ad['image_path'] ?? ''));
    } else {
      $previewImg = trim((string)($ad['image_path'] ?? ''));
      if ($previewImg === '') {
        $previewImg = trim((string)($ad['image_path_en'] ?? ''));
      }
    }
  ?>

  <?php if ($previewImg !== ''): ?>
    <div style="margin-bottom:8px;">
      <img src="<?php echo h(BASE_URL . '/uploads/ads/' . $previewImg); ?>" style="max-width:120px;height:auto;border-radius:10px;">
    </div>
  <?php endif; ?>

  <div style="margin-bottom:6px;">
    <strong>FA:</strong>
    <?php echo h(strip_tags($ad['html'] ?? '')); ?>
  </div>

  <?php if (!empty($ad['html_en'])): ?>
    <div dir="ltr" style="unicode-bidi:plaintext;">
      <strong>EN:</strong>
      <?php echo h(strip_tags($ad['html_en'] ?? '')); ?>
    </div>
  <?php endif; ?>
</td>

      <td><?php echo (int)($ad['sort_order'] ?? 0); ?></td>
      <td><?php echo (int)($ad['is_active'] ?? 0); ?></td>
      <td><?php echo h($ad['image_scope'] ?? 'both'); ?></td>
      <td><?php echo !empty($ad['image_path']) ? 'Yes' : '-'; ?></td>
      <td><?php echo !empty($ad['image_path_en']) ? 'Yes' : '-'; ?></td>
      <td><?php echo h($ad['title'] ?? ''); ?></td>
      <td><?php echo h($ad['position'] ?? ''); ?></td>
      <td><?php echo (int)$ad['id']; ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>