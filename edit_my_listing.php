<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

$page_title = is_en() ? ('Edit Listing | ' . t('site_name')) : ('ویرایش آگهی | ' . t('site_name'));
$page_desc  = is_en() ? 'Edit your submitted listing.' : 'آگهی ثبت‌شده خود را ویرایش کنید.';

$id = (int)($_GET['id'] ?? 0);
$code = trim($_GET['code'] ?? '');

$it = null;

if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('my_listing_by_id')) {
    $it = my_listing_by_id($id);
} elseif ($code !== '' && function_exists('find_listing_by_manage_code_and_id')) {
    $it = find_listing_by_manage_code_and_id($id, $code);
}

if (!$it) {
    require_once __DIR__ . '/inc/header.php';
    echo '<div class="empty">' . h(is_en() ? 'Listing not found or access denied.' : 'آگهی پیدا نشد یا دسترسی مجاز نیست.') . '</div>';
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

$cats = function_exists('categories_all') ? categories_all() : array();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)($_POST['category_id'] ?? 0);
    $titleInput  = trim($_POST['title'] ?? '');
    $bodyInput   = trim($_POST['body'] ?? '');
    $cityInput   = trim($_POST['city'] ?? '');

    if ($category_id <= 0 || $titleInput === '' || $bodyInput === '') {
        $error = is_en() ? 'Please fill in all required fields.' : 'لطفاً فیلدهای لازم را کامل کنید.';
    } else {
        $source_lang = detect_text_lang($titleInput . ' ' . $bodyInput . ' ' . $cityInput);

        if ($source_lang === 'fa') {
            $title_fa = $titleInput;
            $body_fa  = $bodyInput;
            $city_fa  = $cityInput;

            $title_en = !empty($it['title_en']) ? $it['title_en'] : translate_text($titleInput, 'fa', 'en');
            $body_en  = !empty($it['body_en'])  ? $it['body_en']  : translate_text($bodyInput, 'fa', 'en');
            $city_en  = !empty($it['city_en'])  ? $it['city_en']  : translate_text($cityInput, 'fa', 'en');
        } else {
            $title_en = $titleInput;
            $body_en  = $bodyInput;
            $city_en  = $cityInput;

            $title_fa = !empty($it['title']) ? $it['title'] : translate_text($titleInput, 'en', 'fa');
            $body_fa  = !empty($it['body'])  ? $it['body']  : translate_text($bodyInput, 'en', 'fa');
            $city_fa  = !empty($it['city'])  ? $it['city']  : translate_text($cityInput, 'en', 'fa');
        }

        if (function_exists('update_my_listing')) {
            /*
            $ok = update_my_listing($it['id'], array(
                'category_id' => $category_id,
                'title'       => $title_fa,
                'body'        => $body_fa,
                'city'        => $city_fa,
                'title_en'    => $title_en,
                'body_en'     => $body_en,
                'city_en'     => $city_en
            ));*/
            if (function_exists('is_user_logged_in') && is_user_logged_in()) {
                $ok = update_my_listing($it['id'], array(
                    'category_id' => $category_id,
                    'title'       => $title_fa,
                    'body'        => $body_fa,
                    'city'        => $city_fa,
                    'title_en'    => $title_en,
                    'body_en'     => $body_en,
                    'city_en'     => $city_en
                ));
            } else {
                $ok = update_listing_by_manage_code($it['id'], $code, array(
                    'category_id' => $category_id,
                    'title'       => $title_fa,
                    'body'        => $body_fa,
                    'city'        => $city_fa,
                    'title_en'    => $title_en,
                    'body_en'     => $body_en,
                    'city_en'     => $city_en
                ));
            }

            if ($ok) {
                $success = is_en() ? 'Listing updated successfully.' : 'آگهی با موفقیت به‌روزرسانی شد.';
            
                if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('my_listing_by_id')) {
                    $it = my_listing_by_id($it['id']);
                } elseif ($code !== '' && function_exists('find_listing_by_manage_code_and_id')) {
                    $it = find_listing_by_manage_code_and_id($it['id'], $code);
                }
            }            
            else {
                $error = is_en() ? 'Update failed.' : 'به‌روزرسانی انجام نشد.';
            }
        } else {
            $error = is_en() ? 'Update function is not available.' : 'تابع ویرایش در دسترس نیست.';
        }
    }
}

$titleDisplay = is_en() ? ($it['title_en'] ?: $it['title']) : $it['title'];
$bodyDisplay  = is_en() ? ($it['body_en'] ?: $it['body'])   : $it['body'];
$cityDisplay  = is_en() ? ($it['city_en'] ?: $it['city'])   : $it['city'];

require_once __DIR__ . '/inc/header.php';
?>

<h1 class="h1"><?php echo h(is_en() ? 'Edit Listing' : 'ویرایش آگهی'); ?></h1>

<?php if ($error !== ''): ?>
  <div class="error"><?php echo h($error); ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
  <div class="success"><?php echo h($success); ?></div>
<?php endif; ?>

<form method="post" class="formCard">
  <div class="field">
    <label><?php echo h(is_en() ? 'Category' : 'دسته‌بندی'); ?></label>
    <select name="category_id" required>
      <option value=""><?php echo h(t('choose_category')); ?></option>
      <?php foreach ($cats as $c): ?>
        <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)$it['category_id'] === (int)$c['id']) ? 'selected' : ''; ?>>
          <?php echo h(localized_field($c, 'name')); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="field">
    <label><?php echo h(t('city_optional')); ?></label>
    <input type="text" name="city" value="<?php echo h($cityDisplay); ?>">
  </div>

  <div class="field">
    <label><?php echo h(t('listing_title')); ?></label>
    <input type="text" name="title" value="<?php echo h($titleDisplay); ?>" required>
  </div>

  <div class="field">
    <label><?php echo h(t('listing_body')); ?></label>
    <textarea name="body" rows="10" required><?php echo h($bodyDisplay); ?></textarea>
  </div>

  <button class="btn" type="submit"><?php echo h(is_en() ? 'Save Changes' : 'ذخیره تغییرات'); ?></button>
</form>

<?php require_once __DIR__ . '/inc/footer.php'; ?>