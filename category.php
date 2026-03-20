<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

$slug = isset($_GET['c']) ? trim($_GET['c']) : '';
$cat  = $slug ? category_by_slug($slug) : null;

if (!empty($cat) && !empty($cat['name'])) {
    $catName = localized_field($cat, 'name');

    if (is_en()) {
        $page_title = $catName . ' | ' . $site_name = t('site_name');
        $page_desc  = 'Browse the latest listings in the ' . $catName . ' category on ' . t('site_name') . '.';
    } else {
        $page_title = $catName . ' | آگهی‌های ' . $catName . ' | ' . SITE_NAME;
        $page_desc  = 'جدیدترین آگهی‌های دسته ' . $catName . ' را در ' . SITE_NAME . ' مشاهده کنید.';
    }
} else {
    if (is_en()) {
        $page_title = 'Listing Categories | ' . t('site_name');
        $page_desc  = 'Browse listings in different categories on ' . t('site_name') . '.';
    } else {
        $page_title = 'دسته‌بندی آگهی‌ها | ' . SITE_NAME;
        $page_desc  = 'مشاهده آگهی‌ها در دسته‌بندی‌های مختلف در ' . SITE_NAME;
    }
}

if (!$cat) {
    if (is_en()) {
        $page_title = 'Category Not Found | ' . t('site_name');
        $page_desc  = 'The requested category was not found.';
    } else {
        $page_title = 'دسته‌بندی پیدا نشد | ' . SITE_NAME;
        $page_desc  = 'دسته‌بندی مورد نظر پیدا نشد.';
    }

    require_once __DIR__ . '/inc/header.php';
    echo '<div class="empty">' . h(is_en() ? 'Category not found.' : 'دسته‌بندی پیدا نشد.') . '</div>';
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

$current_url = category_url($cat['slug']);

require_once __DIR__ . '/inc/header.php';

$pdo = db();
$stmt = $pdo->prepare("
  SELECT l.*, c.name AS cat_name, c.name_en AS cat_name_en
  FROM listings l
  JOIN categories c ON c.id = l.category_id
  WHERE l.status='approved' AND c.slug=?
  ORDER BY l.id DESC
  LIMIT 50
");
$stmt->execute(array($slug));
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$shownIds = array();
foreach ($list as $row) {
  $shownIds[] = (int)$row['id'];
}
record_listing_impressions($shownIds);

$catName = localized_field($cat, 'name');
?>

<h1 class="h1"><?php echo h($catName); ?></h1>
<p class="muted">
  <?php echo h(is_en() ? ('You are viewing the latest listings in the ' . $catName . ' category.') : ('جدیدترین آگهی‌های دسته ' . $catName . ' را در این صفحه مشاهده می‌کنید.')); ?>
</p>

<div class="listings">
  <?php foreach ($list as $it): ?>
    <?php
      //$imgs = listing_images($it['id']);
      $imgs = listing_images_lang($it['id'], current_lang());
      $thumb = count($imgs) ? (UPLOAD_URL . $imgs[0]['path']) : (BASE_URL . '/assets/noimg.png');

      $titleText = localized_field($it, 'title');
      $cityText  = localized_field($it, 'city');
    ?>
    <a class="card" href="<?php echo h(listing_url($it['id'])); ?>">
      <div class="thumb">
        <img src="<?php echo h($thumb); ?>" alt="<?php echo h($titleText); ?>" loading="lazy" decoding="async">
      </div>
      <div class="meta">
        <div class="title"><?php echo h($titleText); ?></div>
        <div class="sub">
          <?php if (!empty($cityText)): ?>
            <span><?php echo h($cityText); ?></span><span class="dot">•</span>
          <?php endif; ?>
          <span><?php echo h($it['approved_at']); ?></span>
        </div>
      </div>
    </a>
  <?php endforeach; ?>

  <?php if (!count($list)): ?>
    <div class="empty"><?php echo h(is_en() ? 'There are no approved listings in this category yet.' : 'فعلاً آگهی تایید شده‌ای در این دسته وجود ندارد.'); ?></div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>