<?php
/**
 * Seed Educational Listings for LADIVAR (SQLite)
 * Compatible with PHP 5.3/5.4
 *
 * Put in: /inc/seed_educational_listings.php
 * Run once: /inc/seed_educational_listings.php?key=YOUR_SECRET
 * Then DELETE the file.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$RUN_KEY = '1349'; // <-- حتماً عوضش کن

if (!isset($_GET['key']) || $_GET['key'] !== $RUN_KEY) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Forbidden. Use: ?key=YOUR_SECRET\n";
  exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (function_exists('db_init')) db_init();

$pdo = db();

/* -----------------------------
   Seed data (10 educational listings)
------------------------------ */
$seed = array(
  array('category' => 'املاک', 'title' => 'راهنمای کامل اجاره خانه در لس‌آنجلس (ویژه ایرانیان)',
        'body' => "اگر قصد اجاره خانه یا آپارتمان در LA را دارید، بهتر است قبل از اقدام این نکات را بدانید: بررسی credit score، میزان deposit، قرارداد اجاره و قوانین صاحبخانه. در این راهنما نکات اصلی برای جلوگیری از مشکلات رایج توضیح داده شده است.\n\nاین یک آگهی آموزشی برای کمک به کاربران سایت است."),
  array('category' => 'خدمات', 'title' => 'هزینه خدمات لوله‌کشی در LA چقدر است؟',
        'body' => "هزینه لوله‌کشی بسته به نوع کار متفاوت است. باز کردن لوله، تعویض شیرآلات، تعمیر نشتی و نصب تجهیزات هر کدام بازه قیمتی خاصی دارند. این آگهی برای آشنایی اولیه با قیمت‌ها و نحوه انتخاب سرویس‌کار مطمئن تهیه شده است.\n\nاین یک آگهی آموزشی برای کمک به کاربران سایت است."),
  array('category' => 'خودرو', 'title' => 'قبل از خرید ماشین دست دوم در LA این نکات را بخوانید',
        'body' => "قبل از خرید خودرو بهتر است گزارش Carfax بررسی شود، تست رانندگی انجام دهید و هزینه بیمه و رجیستری را محاسبه کنید. در این مطلب نکات مهم برای خرید امن خودرو توضیح داده شده است.\n\nاین یک آگهی آموزشی برای کمک به کاربران سایت است."),
  array('category' => 'خدمات', 'title' => 'نظافت منزل در LA — قیمت‌ها و نکات مهم',
        'body' => "خدمات cleaning در لس‌آنجلس معمولاً بر اساس متراژ خانه و تعداد اتاق‌ها قیمت‌گذاری می‌شود. در این آگهی با میانگین قیمت‌ها و نکات انتخاب سرویس مناسب آشنا می‌شوید.\n\nاین یک آگهی آموزشی برای کمک به کاربران سایت است."),
  array('category' => 'خدمات', 'title' => 'اگر کسب‌وکار دارید، داشتن وب‌سایت ضروری است',
        'body' => "بیشتر مشتریان قبل از تماس، شما را در گوگل جستجو می‌کنند. داشتن وب‌سایت باعث اعتماد بیشتر، جذب مشتری جدید و دیده شدن در نتایج جستجو می‌شود. در این راهنما مزایای داشتن سایت توضیح داده شده است.\n\nاین یک آگهی آموزشی برای کمک به کاربران سایت است."),
  array('category' => 'خدمات', 'title' => 'تبلیغات گوگل در لس‌آنجلس چگونه مشتری می‌آورد؟',
        'body' => "Google Ads کمک می‌کند مشتریانی که دقیقاً دنبال خدمات شما هستند، شما را پیدا کنند. این آگهی توضیح می‌دهد تبلیغات محلی چطور کار می‌کند و چه کسب‌وکارهایی بیشترین نتیجه را می‌گیرند.\n\nاین یک آگهی آموزشی برای کمک به کاربران سایت است."),
  array('category' => 'املاک', 'title' => 'قبل از ساب‌لیز کردن خانه این موارد را بدانید',
        'body' => "ساب‌لیز در LA قوانین خاص خود را دارد. حتماً باید اجازه صاحبخانه بررسی شود و قرارداد به‌درستی نوشته شود تا بعداً مشکلی پیش نیاید.\n\nاین یک آگهی آموزشی برای کمک به کاربران سایت است."),
  array('category' => 'خدمات', 'title' => 'راهنمای انتخاب تعمیرکار قابل اعتماد در لس‌آنجلس',
        'body' => "قبل از انتخاب سرویس‌کار، reviewها، مجوز کاری و تجربه قبلی را بررسی کنید. این راهنما به شما کمک می‌کند انتخاب امن‌تری داشته باشید.\n\nاین یک آگهی آموزشی برای کمک به کاربران سایت است."),
  array('category' => 'خرید و فروش', 'title' => 'چطور وسایل دست دوم خانه را سریع‌تر بفروشیم؟',
        'body' => "عکس مناسب، توضیح کوتاه و قیمت واقعی باعث می‌شود وسایل سریع‌تر فروخته شوند. در این مطلب چند نکته ساده برای فروش موفق آورده شده است.\n\nاین یک آگهی آموزشی برای کمک به کاربران سایت است."),
  array('category' => 'خرید و فروش', 'title' => 'ایمنی در خرید و فروش اینترنتی — نکاتی که باید بدانید',
        'body' => "برای معاملات حضوری بهتر است مکان عمومی انتخاب کنید، از دریافت وجه مطمئن شوید و اطلاعات شخصی اضافه ارائه نکنید. رعایت این نکات از مشکلات احتمالی جلوگیری می‌کند.\n\nاین یک آگهی آموزشی برای کمک به کاربران سایت است."),
);

/* -----------------------------
   Helpers
------------------------------ */
function ensure_category_id(PDO $pdo, $name) {
  $slug = slugify($name);

  // by name
  $st = $pdo->prepare("SELECT id FROM categories WHERE name=? LIMIT 1");
  $st->execute(array($name));
  $id = $st->fetchColumn();
  if ($id) return (int)$id;

  // by slug
  $st = $pdo->prepare("SELECT id FROM categories WHERE slug=? LIMIT 1");
  $st->execute(array($slug));
  $id = $st->fetchColumn();
  if ($id) return (int)$id;

  // insert (NOTE: categories requires sort_order + created_at)
  $st = $pdo->prepare("INSERT INTO categories(name,slug,sort_order,created_at) VALUES(?,?,?,?)");
  $st->execute(array($name, $slug, 0, now()));
  return (int)$pdo->lastInsertId();
}

function listing_exists(PDO $pdo, $title) {
  $st = $pdo->prepare("SELECT 1 FROM listings WHERE title=? LIMIT 1");
  $st->execute(array($title));
  return (bool)$st->fetchColumn();
}

/* -----------------------------
   Insert
------------------------------ */
$inserted = 0;
$skipped  = 0;

foreach ($seed as $item) {
  $title = $item['title'];

  if (listing_exists($pdo, $title)) {
    $skipped++;
    continue;
  }

  $catId = ensure_category_id($pdo, $item['category']);

  // NOTE: index.php only shows status='approved'
  $st = $pdo->prepare("INSERT INTO listings(category_id,title,body,city,status,created_at,approved_at)
                       VALUES(?,?,?,?,?,?,?)");
  $st->execute(array(
    $catId,
    $title,
    $item['body'],
    'Los Angeles',
    'approved',
    now(),
    now()
  ));

  $inserted++;
}

header('Content-Type: text/plain; charset=utf-8');
echo "Done.\n";
echo "Inserted: " . $inserted . "\n";
echo "Skipped (already existed): " . $skipped . "\n\n";
echo "IMPORTANT: Delete this seed file now.\n";