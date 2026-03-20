<?php
require_once __DIR__ . '/inc/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$pdo = db();

$base =
(
  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  ? 'https://'
  : 'http://'
)
. $_SERVER['HTTP_HOST']
. BASE_URL;

$urls = array();

/* صفحه اصلی */

$urls[] = array(
  'loc' => $base . '/index.php',
  'priority' => '1.0'
);

/* دسته بندی ها */

foreach (categories_all() as $c) {

  $urls[] = array(
    'loc' => $base . '/category.php?c=' . urlencode($c['slug']),
    'priority' => '0.9'
  );

}

/* آگهی ها */

$stmt = $pdo->query(
"SELECT id, approved_at
 FROM listings
 WHERE status='approved'
 ORDER BY id DESC
 LIMIT 2000"
);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

  $urls[] = array(
    'loc' => $base . '/listing.php?id=' . (int)$r['id'],
    'lastmod' => !empty($r['approved_at']) ? date('c', strtotime($r['approved_at'])) : '',
    'priority' => '0.8'
  );

}

/* خروجی XML */

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach($urls as $u) {

  echo "  <url>\n";

  echo "    <loc>" . htmlspecialchars($u['loc'], ENT_QUOTES, 'UTF-8') . "</loc>\n";

  if (!empty($u['lastmod'])) {
    echo "    <lastmod>" . $u['lastmod'] . "</lastmod>\n";
  }

  echo "    <changefreq>daily</changefreq>\n";

  echo "    <priority>" . $u['priority'] . "</priority>\n";

  echo "  </url>\n";

}

echo "</urlset>";





/*
require_once __DIR__ . '/inc/functions.php';
header('Content-Type: application/xml; charset=utf-8');

$pdo = db();
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . BASE_URL;

$urls = array();
$urls[] = $base . '/index.php';

foreach (categories_all() as $c) {
  $urls[] = $base . '/category.php?c=' . urlencode($c['slug']);
}

$stmt = $pdo->query("SELECT id FROM listings WHERE status='approved' ORDER BY id DESC LIMIT 2000");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $urls[] = $base . '/listing.php?id=' . (int)$r['id'];
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach($urls as $u) {
  echo "  <url><loc>" . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . "</loc></url>\n";
}
echo "</urlset>";
*/