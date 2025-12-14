<?php
/**
 * RDYS - Core Configuration & Database Connection
 * * @author Senior Developer
 * @version 2.0.0
 * * این فایل قلب سیستم است. تمام تنظیمات حیاتی و اتصال به دیتابیس در اینجا انجام می‌شود.
 */

// 1. امنیت: جلوگیری از دسترسی مستقیم به این فایل
// اگر کسی تلاش کند rdys.ir/config.php را باز کند، دسترسی قطع می‌شود.
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    die('<!DOCTYPE html><html><body style="background:#15202B;color:#F91880;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;"><h1>Access Denied / دسترسی غیرمجاز</h1></body></html>');
}

// -----------------------------------------------------------------------------
// 2. تنظیمات اصلی (Configuration)
// -----------------------------------------------------------------------------

// الف) تنظیمات پایگاه داده (این بخش را طبق هاست خود ویرایش کنید)
define('DB_HOST', 'localhost');
define('DB_NAME', 'readystu_rdys');
define('DB_USER', 'readystu_rdys');
define('DB_PASS', 'Jb6gxgAWa4MZrPPLYmXe');

// ب) آدرس کامل سایت (حتما با / در انتها)
// مثال: https://rdys.ir/
define('SITE_URL', 'https://rdys.ir/');

// پ) منطقه زمانی
date_default_timezone_set('Asia/Tehran');

// ت) حالت دیباگ (برای زمان توسعه true و برای زمان انتشار false باشد)
define('DEBUG_MODE', true);

// -----------------------------------------------------------------------------
// 3. تنظیمات PHP و امنیت سشن
// -----------------------------------------------------------------------------

// تنظیم نمایش خطاها بر اساس حالت دیباگ
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// تنظیمات امنیتی سشن قبل از شروع
// جلوگیری از دسترسی جاوااسکریپت به کوکی سشن (XSS Protection)
ini_set('session.cookie_httponly', 1);
// استفاده از کوکی فقط در HTTPS (اگر SSL دارید این را فعال کنید)
// ini_set('session.cookie_secure', 1); 
ini_set('session.use_only_cookies', 1);

session_start();

// -----------------------------------------------------------------------------
// 4. سیستم هوک (Plugin System Architecture)
// -----------------------------------------------------------------------------
// این سیستم اجازه می‌دهد در آینده قابلیت‌هایی را بدون تغییر هسته اضافه کنید.

global $rdys_hooks;
$rdys_hooks = [];

/**
 * افزودن یک تابع به یک هوک خاص
 * @param string $hook_name نام هوک (مثلا: after_shorten)
 * @param callable $callback تابع اجرایی
 */
function add_action($hook_name, $callback) {
    global $rdys_hooks;
    $rdys_hooks[$hook_name][] = $callback;
}

/**
 * اجرای تمام توابع متصل به یک هوک
 * @param string $hook_name نام هوک
 * @param array $args آرگومان‌های ارسالی به توابع
 */
function do_action($hook_name, $args = []) {
    global $rdys_hooks;
    if (isset($rdys_hooks[$hook_name])) {
        foreach ($rdys_hooks[$hook_name] as $callback) {
            call_user_func_array($callback, $args);
        }
    }
}

// -----------------------------------------------------------------------------
// 5. اتصال به پایگاه داده (PDO Connection)
// -----------------------------------------------------------------------------

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // پرتاب خطا در صورت بروز مشکل
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // خروجی به صورت آرایه انجمنی
        PDO::ATTR_EMULATE_PREPARES   => false,                // استفاده از PreparedStatement واقعی (امنیت بالا)
        PDO::ATTR_PERSISTENT         => false,                // اتصال غیرپایدار (بهتر برای شیرهاستینگ)
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (\PDOException $e) {
    // نمایش صفحه خطای زیبا به جای چاپ Stack Trace (امنیت + تجربه کاربری)
    // این بخش HTML خالص است چون هنوز سیستم لود نشده
    die('
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>خطای سیستم | RDYS</title>
        <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
        <style>
            body { background-color: #15202B; color: #F7F9F9; font-family: "Vazirmatn", sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .card { background: #192734; border: 1px solid #38444D; padding: 2rem; border-radius: 1rem; text-align: center; max-width: 400px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5); }
            h1 { color: #F91880; margin-top: 0; }
            p { color: #8899A6; line-height: 1.6; }
            code { background: #15202B; padding: 0.2rem 0.4rem; border-radius: 4px; font-family: monospace; color: #F91880; display: block; margin: 10px 0; direction: ltr; }
            .btn { background: #1D9BF0; color: white; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 9999px; display: inline-block; margin-top: 1rem; font-weight: bold; }
            .btn:hover { background-color: #1A8CD8; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>خطای پایگاه داده</h1>
            <p>ارتباط با دیتابیس برقرار نشد. لطفا فایل <code>config.php</code> را بررسی کنید.</p>
            ' . (DEBUG_MODE ? '<code>' . $e->getMessage() . '</code>' : '') . '
            <p style="font-size: 0.9rem;">اگر اولین بار است که اسکریپت را اجرا می‌کنید، ابتدا دیتابیس را بسازید.</p>
            <a href="install.php" class="btn">نصب دیتابیس (Install)</a>
        </div>
    </body>
    </html>
    ');
}

/**
 * تابع کمکی برای دریافت آدرس کامل سایت
 * اگر مسیری داده شود، به انتهای آدرس سایت اضافه می‌کند.
 */
function site_url($path = '') {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}
?>