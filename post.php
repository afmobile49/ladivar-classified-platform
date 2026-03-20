<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

$hide_side_ads = 1; // در صفحه ثبت آگهی تبلیغات کناری نمایش داده نشود

//$page_title = 'ثبت آگهی | ' . (defined('SITE_NAME') ? SITE_NAME : 'Site');
//$page_desc  = 'ثبت آگهی جدید';

$page_title = t('post_ad') . ' | ' . t('site_name');
$page_desc  = is_en() ? 'Create a new listing' : 'ثبت آگهی جدید';

require_once __DIR__ . '/inc/header.php';

/* ---------------- Helpers (safe) ---------------- */
function table_columns(PDO $pdo, string $table): array {
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  $cols = [];
  try {
    if ($driver === 'sqlite') {
      $st = $pdo->query("PRAGMA table_info($table)");
      $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
      foreach ($rows as $r) $cols[] = (string)$r['name'];
    } else {
      // MySQL/MariaDB
      $st = $pdo->query("DESCRIBE `$table`");
      $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
      foreach ($rows as $r) $cols[] = (string)$r['Field'];
    }
  } catch (Throwable $e) {
    // اگر دسترسی نداشت یا جدول نبود
  }
  return $cols;
}

function col_exists(array $cols, string $name): bool {
  return in_array($name, $cols, true);
}

function safe_is_user_logged_in(): bool {
  return function_exists('is_user_logged_in') ? (bool)is_user_logged_in() : (!empty($_SESSION['user_id']));
}

function safe_make_edit_token(int $bytes = 16): string {
  if (function_exists('make_edit_token')) return (string)make_edit_token($bytes);
  try {
    return bin2hex(random_bytes($bytes)); // 32 hex when 16 bytes
  } catch (Throwable $e) {
    return bin2hex(openssl_random_pseudo_bytes($bytes));
  }
}

/* ---------------- Data ---------------- */
$pdo  = function_exists('db') ? db() : null;
$cats = function_exists('categories_all') ? categories_all() : [];

$errors = [];
$ok = false;

$manage_link = '';
$manage_code = '';

/* حفظ مقدارهای فرم بعد از خطا */
$form = [
  'category_id' => (int)($_POST['category_id'] ?? 0),
  'title'       => trim($_POST['title'] ?? ''),
  'body'        => trim($_POST['body'] ?? ''),
  'city'        => trim($_POST['city'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $category_id = $form['category_id'];
  $title       = $form['title'];
  $body        = $form['body'];
  $city        = $form['city'];
  
  $source_lang = detect_text_lang($title . ' ' . $body . ' ' . $city);

if ($source_lang === 'fa') {
  $title_fa = $title;
  $body_fa  = $body;
  $city_fa  = $city;

  $title_en = translate_text($title, 'fa', 'en');
  $body_en  = translate_text($body, 'fa', 'en');
  $city_en  = translate_text($city, 'fa', 'en');
} else {
  $title_en = $title;
  $body_en  = $body;
  $city_en  = $city;

  $title_fa = translate_text($title, 'en', 'fa');
  $body_fa  = translate_text($body, 'en', 'fa');
  $city_fa  = translate_text($city, 'en', 'fa');
}


  

  if ($category_id <= 0) $errors[] = 'لطفاً دسته‌بندی را انتخاب کنید.';
  if ($title === '' || (defined('MAX_TITLE_LEN') && mb_strlen($title,'UTF-8') > MAX_TITLE_LEN)) $errors[] = 'عنوان معتبر نیست.';
  if ($body  === '' || (defined('MAX_BODY_LEN')  && mb_strlen($body,'UTF-8')  > MAX_BODY_LEN )) $errors[] = 'متن آگهی معتبر نیست.';

  // تصاویر
  $files = [];
  if (!empty($_FILES['img1']) && ($_FILES['img1']['name'] ?? '') !== '') $files[] = $_FILES['img1'];
  if (!empty($_FILES['img2']) && ($_FILES['img2']['name'] ?? '') !== '') $files[] = $_FILES['img2'];

  if (count($files) > 2) $errors[] = 'حداکثر ۲ تصویر مجاز است.';

  // اگر validate_image_upload وجود دارد استفاده کن، وگرنه خیلی ساده چک کن
  foreach ($files as $f) {
    if (function_exists('validate_image_upload')) {
      $msg = validate_image_upload($f);
      if ($msg) $errors[] = $msg;
    } else {
      // حداقل چک: بدون خطای upload
      if (!empty($f['error'])) $errors[] = 'آپلود تصویر مشکل دارد.';
    }
  }

  if (!count($errors)) {
    if (!$pdo) {
      $errors[] = 'اتصال دیتابیس برقرار نیست.';
    } else {
      try {
        $pdo->beginTransaction();

        $cols = table_columns($pdo, 'listings');

        $user_id = safe_is_user_logged_in() ? (int)($_SESSION['user_id'] ?? 0) : null;
        if ($user_id === 0) $user_id = null;

        $edit_token = null;
        if ($user_id === null && col_exists($cols, 'edit_token')) {
          $edit_token = safe_make_edit_token(16);
        }

        // created_at
        $created_at = function_exists('now') ? now() : date('Y-m-d H:i:s');

        // INSERT را بر اساس ستون‌های واقعی جدول بساز
        //$fields = ['category_id','title','body','city','status','created_at'];
        //$values = [$category_id, $title, $body, $city, 'pending', $created_at];


        $fields = ['category_id','title','body','city','status','created_at'];
        $values = [$category_id, $title_fa, $body_fa, $city_fa, 'pending', $created_at];
        
        if (col_exists($cols, 'title_en')) {
          $fields[] = 'title_en';
          $values[] = $title_en;
        }
        if (col_exists($cols, 'body_en')) {
          $fields[] = 'body_en';
          $values[] = $body_en;
        }
        if (col_exists($cols, 'city_en')) {
          $fields[] = 'city_en';
          $values[] = $city_en;
        }
        if (col_exists($cols, 'source_lang')) {
          $fields[] = 'source_lang';
          $values[] = $source_lang;
        }



        if (col_exists($cols, 'user_id')) {
          $fields[] = 'user_id';
          $values[] = $user_id;
        }
        if (col_exists($cols, 'edit_token')) {
          $fields[] = 'edit_token';
          $values[] = $edit_token;
        }

        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO listings(" . implode(',', $fields) . ") VALUES($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $listing_id = (int)$pdo->lastInsertId();

        // تصاویر
        foreach ($files as $f) {
          $saved = null;

          if (function_exists('save_uploaded_image')) {
            $saved = save_uploaded_image($f);
          } else {
            // اگر تابع ذخیره نداری، اینجا آپلود انجام نمی‌دهیم تا سایت خراب نشود
            $saved = null;
          }

          if ($saved) {
            $stmt2 = $pdo->prepare("INSERT INTO listing_images(listing_id,path,created_at) VALUES(?,?,?)");
            $stmt2->execute([$listing_id, $saved, $created_at]);
          }
        }

        $pdo->commit();
        $ok = true;

        // لینک مدیریت فقط اگر edit_token واقعاً ذخیره شده باشد
        if ($user_id === null && !empty($edit_token)) {
          $manage_link = rtrim((string)BASE_URL, '/') . "/manage.php?id=" . $listing_id . "&token=" . urlencode($edit_token);
          $manage_code = $edit_token;
        }

        // پاک کردن فرم بعد از ثبت موفق
        $form = ['category_id'=>0,'title'=>'','body'=>'','city'=>''];

      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = 'خطا در ثبت آگهی: ' . $e->getMessage();
      }
    }
  }
}
?>

<h1 class="h1"><?php echo h(t('post_ad')); ?></h1>

<?php if (safe_is_user_logged_in()): ?>
<div class="success"><?php echo h(is_en() ? 'You are logged in. Your listing will be connected to your profile if the profile system is active.' : 'شما وارد شده‌اید. آگهی به پروفایل شما وصل می‌شود (اگر سیستم پروفایل فعال باشد).'); ?></div>
<?php else: ?>



<div class="muted">
  <?php if (is_en()): ?>
    To edit your listing more easily later, you may
    <a class="tag" href="<?php echo h(append_lang_to_url(BASE_URL . '/login.php')); ?>">Login</a>
    or
    <a class="tag" href="<?php echo h(append_lang_to_url(BASE_URL . '/register.php')); ?>">Register</a>.
    (Optional)
  <?php else: ?>
    برای اینکه بعداً راحت آگهی را ویرایش کنید می‌توانید
    <a class="tag" href="<?php echo h(append_lang_to_url(BASE_URL . '/login.php')); ?>">ورود</a>
    یا
    <a class="tag" href="<?php echo h(append_lang_to_url(BASE_URL . '/register.php')); ?>">ثبت‌نام</a>
    کنید. (اختیاری)
  <?php endif; ?>
</div>
  
  
<?php endif; ?>

<?php if ($ok): ?>
  <div class="success">

<?php echo h(is_en() ? 'Your listing was submitted and will be published after admin approval.' : 'آگهی شما ثبت شد و بعد از تایید ادمین منتشر می‌شود.'); ?>

    <?php if ($manage_link): ?>
      <div style="margin-top:10px;">

<strong><?php echo h(is_en() ? 'Management link (save this for later editing):' : 'لینک مدیریت (برای ویرایش بعدی نگه دارید):'); ?></strong><br>

        <a dir="ltr" style="unicode-bidi:plaintext;display:inline-block;" href="<?php echo h($manage_link); ?>"><?php echo h($manage_link); ?></a>

<div class="muted" style="margin-top:6px;"><?php echo h(is_en() ? 'Management code:' : 'کد مدیریت:'); ?> <span dir="ltr" style="unicode-bidi:plaintext;"><?php echo h($manage_code); ?></span></div>

      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (count($errors)): ?>
  <div class="error">
    <?php foreach($errors as $e) echo '<div>'.h($e).'</div>'; ?>
  </div>
<?php endif; ?>

<form class="form" method="post" enctype="multipart/form-data">

<label><?php echo h(is_en() ? 'Category' : 'دسته‌بندی'); ?></label>

  <select name="category_id" required>
<option value=""><?php echo h(t('choose_category')); ?></option>
    <?php foreach($cats as $c): ?>
      <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)$form['category_id']===(int)$c['id'])?'selected':''; ?>>
<?php echo h(localized_field($c, 'name')); ?>
      </option>
    <?php endforeach; ?>
  </select>

<label><?php echo h(t('city_optional')); ?></label>

  <input type="text" name="city" value="<?php echo h($form['city']); ?>" placeholder="">

<label><?php echo h(t('listing_title')); ?></label>

  <input type="text" name="title" value="<?php echo h($form['title']); ?>" maxlength="<?php echo defined('MAX_TITLE_LEN')?(int)MAX_TITLE_LEN:120; ?>" required>

<label><?php echo h(t('listing_body')); ?></label>
  <textarea name="body" maxlength="<?php echo defined('MAX_BODY_LEN')?(int)MAX_BODY_LEN:2000; ?>" rows="6" required><?php echo h($form['body']); ?></textarea>

<label><?php echo h(t('image_1_optional')); ?></label>

  <input type="file" name="img1" accept="image/*">

<label><?php echo h(t('image_2_optional')); ?></label>
  <input type="file" name="img2" accept="image/*">

<button class="btn" type="submit"><?php echo h(t('submit_for_review')); ?></button>
</form>

<?php require_once __DIR__ . '/inc/footer.php'; ?>