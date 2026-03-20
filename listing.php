<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$pdo = db();
$stmt = $pdo->prepare("
  SELECT l.*, c.name AS cat_name, c.name_en AS cat_name_en, c.slug AS cat_slug
  FROM listings l
  JOIN categories c ON c.id=l.category_id
  WHERE l.id=? AND l.status='approved'
");
$stmt->execute(array($id));
$it = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$it) {
  $page_title = is_en() ? ('Listing Not Found | ' . t('site_name')) : ('آگهی پیدا نشد | ' . SITE_NAME);
  $page_desc  = is_en() ? 'The requested listing was not found or has not been approved yet.' : 'آگهی مورد نظر پیدا نشد یا هنوز تایید نشده است.';
  require_once __DIR__ . '/inc/header.php';
  echo '<div class="empty">' . h(is_en() ? 'Listing not found or not approved yet.' : 'آگهی پیدا نشد یا هنوز تایید نشده است.') . '</div>';
  require_once __DIR__ . '/inc/footer.php';
  exit;
}

record_listing_detail_view($it['id']);
$stats = listing_view_stats($it['id']);

$titleText = localized_field($it, 'title');
$bodyText  = localized_field($it, 'body');
$cityText  = localized_field($it, 'city');
$catText   = localized_field($it, 'cat_name', 'cat_name_en');

if (is_en()) {
  $page_title = $titleText
    . (!empty($cityText) ? ' in ' . $cityText : '')
    . ' | ' . $catText
    . ' | ' . t('site_name');
} else {
  $page_title = $titleText
    . (!empty($cityText) ? ' در ' . $cityText : '')
    . ' | ' . $catText
    . ' | ' . SITE_NAME;
}

$page_desc = mb_substr($bodyText, 0, 150, 'UTF-8');

if (!empty($cityText)) {
  $page_desc .= is_en() ? (' - ' . $cityText) : (' - ' . $cityText);
}

if (!empty($catText)) {
  $page_desc .= is_en() ? (' - Category: ' . $catText) : (' - دسته ' . $catText);
}

//$imgs = listing_images($it['id']);
$imgs = listing_images_lang($it['id'], current_lang());

$og_image = '';

if (!empty($imgs) && !empty($imgs[0]['path'])) {
  $og_image = UPLOAD_URL . $imgs[0]['path'];
}

$current_url = listing_full_url($it['id']);

require_once __DIR__ . '/inc/header.php';
?>

<nav class="breadcrumb">
  <a href="<?php echo h(append_lang_to_url(BASE_URL . '/')); ?>"><?php echo h(t('home')); ?></a>
  <span class="dot">•</span>
  <a href="<?php echo h(category_url($it['cat_slug'])); ?>"><?php echo h($catText); ?></a>
  <span class="dot">•</span>
  <span><?php echo h($titleText); ?></span>
</nav>

<h1 class="h1"><?php echo h($titleText); ?></h1>

<div class="subline">
  <a href="<?php echo h(category_url($it['cat_slug'])); ?>" class="tag"><?php echo h($catText); ?></a>
  <?php if ($cityText !== ''): ?><span class="dot">•</span><span><?php echo h($cityText); ?></span><?php endif; ?>
  <span class="dot">•</span><span><?php echo h($it['approved_at']); ?></span>
  <span class="dot">•</span><span><?php echo h(is_en() ? 'List views:' : 'نمایش در لیست:'); ?> <?php echo (int)$stats['list_view_count']; ?></span>
  <span class="dot">•</span><span><?php echo h(is_en() ? 'Detail views:' : 'ورود به آگهی:'); ?> <?php echo (int)$stats['detail_view_count']; ?></span>
</div>

<div class="gallery">
  <?php foreach($imgs as $im): ?>
    <img src="<?php echo h(UPLOAD_URL . $im['path']); ?>" alt="<?php echo h($titleText); ?>" loading="lazy" decoding="async">
  <?php endforeach; ?>
</div>

<div class="body"><?php echo nl2br(h($bodyText)); ?></div>

<script type="application/ld+json">
<?php
$img0 = count($imgs) ? (UPLOAD_URL . $imgs[0]['path']) : '';
$page_url = listing_full_url($it['id']);

$data = array(
  "@context" => "https://schema.org",
  "@type" => "Offer",
  "name" => $titleText,
  "description" => mb_substr($bodyText, 0, 300, 'UTF-8'),
  "url" => $page_url,
  "category" => $catText,
  "datePosted" => $it['approved_at']
);

if (!empty($cityText)) {
  $data["address"] = array(
    "@type" => "PostalAddress",
    "addressLocality" => $cityText
  );
}

if ($img0) {
  $data["image"] = $img0;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
?>
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>