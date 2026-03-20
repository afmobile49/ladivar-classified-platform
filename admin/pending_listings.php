<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_admin();
require_once __DIR__ . '/../inc/header.php';

$pdo = db();

function admin_pending_has_column(PDO $pdo, string $table, string $col): bool {
  try {
    $rows = $pdo->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      if (($r['name'] ?? '') === $col) return true;
    }
  } catch (Throwable $e) {
    // ignore
  }
  return false;
}

$has_title_en    = admin_pending_has_column($pdo, 'listings', 'title_en');
$has_body_en     = admin_pending_has_column($pdo, 'listings', 'body_en');
$has_city_en     = admin_pending_has_column($pdo, 'listings', 'city_en');
$has_source_lang = admin_pending_has_column($pdo, 'listings', 'source_lang');

$selectCols = "
  l.*,
  c.name AS cat_name,
  COALESCE(l.list_view_count, 0) AS list_view_count,
  COALESCE(l.detail_view_count, 0) AS detail_view_count
";

if ($has_title_en)    $selectCols .= ", COALESCE(l.title_en, '') AS title_en";
if ($has_body_en)     $selectCols .= ", COALESCE(l.body_en, '') AS body_en";
if ($has_city_en)     $selectCols .= ", COALESCE(l.city_en, '') AS city_en";
if ($has_source_lang) $selectCols .= ", COALESCE(l.source_lang, 'fa') AS source_lang";

$sql = "
  SELECT $selectCols
  FROM listings l
  LEFT JOIN categories c ON c.id = l.category_id
  WHERE l.status = 'pending'
  ORDER BY l.id DESC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function pending_text_ok($v): bool {
  return trim((string)$v) !== '';
}

function pending_translation_state(array $row, bool $has_title_en, bool $has_body_en, bool $has_city_en, bool $has_source_lang): array {
  $source = $has_source_lang ? ($row['source_lang'] ?? 'fa') : 'fa';
  if (!in_array($source, ['fa', 'en'], true)) {
    $source = 'fa';
  }

  $fa_ok = pending_text_ok($row['title'] ?? '') && pending_text_ok($row['body'] ?? '');
  $en_ok = true;

  if ($has_title_en) $en_ok = $en_ok && pending_text_ok($row['title_en'] ?? '');
  if ($has_body_en)  $en_ok = $en_ok && pending_text_ok($row['body_en'] ?? '');

  $city_pair_ok = true;
  if ($has_city_en) {
    $fa_city = trim((string)($row['city'] ?? ''));
    $en_city = trim((string)($row['city_en'] ?? ''));

    if ($fa_city !== '' && $en_city === '') $city_pair_ok = false;
    if ($source === 'en' && $en_city !== '' && $fa_city === '') $city_pair_ok = false;
  }

  $full_bilingual = $fa_ok && $en_ok && $city_pair_ok;

  if ($source === 'fa') {
    if (!$fa_ok) {
      $status = 'مشکل در متن فارسی';
      $class  = 'bad';
    } elseif (!$en_ok || !$city_pair_ok) {
      $status = 'نیازمند ترجمه انگلیسی';
      $class  = 'warn';
    } else {
      $status = 'آماده تایید';
      $class  = 'good';
    }
  } else {
    if (!$en_ok) {
      $status = 'مشکل در متن انگلیسی';
      $class  = 'bad';
    } elseif (!$fa_ok || !$city_pair_ok) {
      $status = 'نیازمند ترجمه فارسی';
      $class  = 'warn';
    } else {
      $status = 'آماده تایید';
      $class  = 'good';
    }
  }

  return [
    'source' => $source,
    'fa_ok' => $fa_ok,
    'en_ok' => $en_ok,
    'city_pair_ok' => $city_pair_ok,
    'full_bilingual' => $full_bilingual,
    'label' => $status,
    'class' => $class,
  ];
}
?>

<style>
.pendingWrap { margin-top: 18px; }
.pendingSummary {
  display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;
}
.pendingBadge {
  padding:8px 12px; border-radius:10px; background:#f3f3f3;
}
.pendingTableWrap { overflow-x:auto; }
.pendingTable {
  width:100%;
  border-collapse:collapse;
  background:#fff;
}
.pendingTable th, .pendingTable td {
  border:1px solid #e5e5e5;
  padding:10px;
  vertical-align:top;
  text-align:right;
}
.pendingTable th {
  background:#fafafa;
}
.pendingStatus {
  display:inline-block;
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
  white-space:nowrap;
}
.pendingStatus.good { background:#e8f7e8; }
.pendingStatus.warn { background:#fff4d6; }
.pendingStatus.bad  { background:#fde8e8; }
.pendingMini {
  font-size:12px;
  color:#666;
  line-height:1.7;
}
.pendingTitle {
  font-weight:bold;
  margin-bottom:5px;
}
.pendingActions {
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}
.pendingThumb {
  width:72px;
  height:72px;
  object-fit:cover;
  border-radius:10px;
  border:1px solid #ddd;
}
</style>

<h1 class="h1">آگهی‌های در انتظار تایید</h1>

<?php
$total = count($rows);
$ready = 0;
$needTranslation = 0;
$bad = 0;

foreach ($rows as $r) {
  $state = pending_translation_state($r, $has_title_en, $has_body_en, $has_city_en, $has_source_lang);
  if ($state['class'] === 'good') $ready++;
  elseif ($state['class'] === 'warn') $needTranslation++;
  else $bad++;
}
?>

<div class="pendingWrap">
  <div class="pendingSummary">
    <div class="pendingBadge">کل آگهی‌های pending: <strong><?php echo (int)$total; ?></strong></div>
    <div class="pendingBadge">آماده تایید: <strong><?php echo (int)$ready; ?></strong></div>
    <div class="pendingBadge">نیازمند ترجمه: <strong><?php echo (int)$needTranslation; ?></strong></div>
    <div class="pendingBadge">دارای مشکل متنی: <strong><?php echo (int)$bad; ?></strong></div>
  </div>

  <?php if (!$total): ?>
    <div class="empty">هیچ آگهی pending وجود ندارد.</div>
  <?php else: ?>
    <div class="pendingTableWrap">
      <table class="pendingTable">
        <thead>
          <tr>
            <th>#</th>
            <th>تصویر</th>
            <th>عنوان / دسته</th>
            <th>وضعیت ترجمه</th>
            <th>جزئیات</th>
            <th>اقدام</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <?php
              $state = pending_translation_state($r, $has_title_en, $has_body_en, $has_city_en, $has_source_lang);
              $imgs = listing_images((int)$r['id']);
              $thumb = (is_array($imgs) && count($imgs))
                ? (UPLOAD_URL . $imgs[0]['path'])
                : (BASE_URL . '/assets/noimg.png');
            ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>

              <td>
                <img class="pendingThumb" src="<?php echo h($thumb); ?>" alt="thumb">
              </td>

              <td>
                <div class="pendingTitle"><?php echo h($r['title'] ?? ''); ?></div>
                <?php if (!empty($r['title_en'])): ?>
                  <div class="pendingMini" dir="ltr" style="unicode-bidi:plaintext;"><?php echo h($r['title_en']); ?></div>
                <?php endif; ?>
                <div class="pendingMini">
                  دسته: <?php echo h($r['cat_name'] ?? '-'); ?>
                  <?php if (!empty($r['city'])): ?>
                    <span class="dot">•</span> شهر: <?php echo h($r['city']); ?>
                  <?php endif; ?>
                </div>
              </td>

              <td>
                <span class="pendingStatus <?php echo h($state['class']); ?>">
                  <?php echo h($state['label']); ?>
                </span>
                <div class="pendingMini" style="margin-top:8px;">
                  زبان مبدا: <strong><?php echo h($state['source']); ?></strong>
                </div>
              </td>

              <td>
                <div class="pendingMini">
                  فارسی:
                  <?php echo $state['fa_ok'] ? '✅ کامل' : '❌ ناقص'; ?>
                </div>
                <div class="pendingMini">
                  انگلیسی:
                  <?php echo $state['en_ok'] ? '✅ کامل' : '❌ ناقص'; ?>
                </div>
                <div class="pendingMini">
                  شهر دوطرفه:
                  <?php echo $state['city_pair_ok'] ? '✅' : '❌'; ?>
                </div>
                <div class="pendingMini">
                  نمایش لیست: <?php echo (int)$r['list_view_count']; ?>
                  <span class="dot">•</span>
                  ورود به آگهی: <?php echo (int)$r['detail_view_count']; ?>
                </div>
              </td>

              <td>
                <div class="pendingActions">
                  <a class="tag" href="<?php echo h(BASE_URL . '/admin/edit_listing.php?id=' . (int)$r['id']); ?>">
                    ویرایش / ترجمه
                  </a>

                  <?php if ($state['full_bilingual']): ?>
                    <a class="tag" href="<?php echo h(BASE_URL . '/admin/edit_listing.php?id=' . (int)$r['id']); ?>">
                      آماده تایید
                    </a>
                  <?php endif; ?>

                  <a class="tag" href="<?php echo h(listing_url((int)$r['id'])); ?>" target="_blank" rel="noopener">
                    مشاهده
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>