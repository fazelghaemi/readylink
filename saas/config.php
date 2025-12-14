<?php
/**
 * RDYS - Enterprise Core Configuration
 * Theme: Ready Studio (Teal & Black)
 * Version: 10.0.0 (Maximized)
 * * This file handles Database connection, Security headers, Session management,
 * * Dynamic URL detection, and Global Hook system.
 */

// 1. Security: Block Direct Access to Config
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header('HTTP/1.1 403 Forbidden');
    die('<!DOCTYPE html><html><body style="background:#010101;color:#FF3B30;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;margin:0;"><h1>⛔ Access Denied</h1></body></html>');
}

// -----------------------------------------------------------------------------
// 2. Database Credentials (تنظیمات دیتابیس اختصاصی شما)
// -----------------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'mihansms_LinkShortener');
define('DB_USER', 'mihansms_LinkShortener');
define('DB_PASS', 'xK]~SmTG)j,G;)bb');
define('DB_CHARSET', 'utf8mb4');

// -----------------------------------------------------------------------------
// 3. Environment & Error Handling (مدیریت خطا و محیط)
// -----------------------------------------------------------------------------
// تشخیص خودکار محیط لوکال یا سرور
$whitelist = ['127.0.0.1', '::1', 'localhost'];
define('IS_LOCALHOST', in_array($_SERVER['REMOTE_ADDR'] ?? '', $whitelist));

// تنظیم نمایش خطاها
if (IS_LOCALHOST || isset($_GET['debug'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    define('DEBUG_MODE', true);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    define('DEBUG_MODE', false);
}

// -----------------------------------------------------------------------------
// 4. Dynamic Site URL (تشخیص خودکار آدرس سایت)
// -----------------------------------------------------------------------------
// این بخش باعث می‌شود اسکریپت روی هر دامنه‌ای (ساب‌دامین یا پوشه) کار کند
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptPath = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
define('SITE_URL', $protocol . $host . $scriptPath);

// منطقه زمانی
date_default_timezone_set('Asia/Tehran');

// -----------------------------------------------------------------------------
// 5. Secure Session Management (مدیریت امن نشست‌ها)
// -----------------------------------------------------------------------------
// تنظیمات کوکی برای جلوگیری از حملات XSS و CSRF
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); // غیرقابل دسترسی با JS
    ini_set('session.use_only_cookies', 1); // فقط کوکی (نه URL)
    ini_set('session.cookie_samesite', 'Lax'); // محافظت CSRF
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1); // فقط روی HTTPS
    }
    
    // افزایش طول عمر سشن (مثلا 1 روز)
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    
    session_start();
}

// -----------------------------------------------------------------------------
// 6. Hook System (سیستم توسعه‌پذیری)
// -----------------------------------------------------------------------------
global $rdys_hooks;
$rdys_hooks = [];

function add_action($hook_name, $callback, $priority = 10) {
    global $rdys_hooks;
    $rdys_hooks[$hook_name][] = ['callback' => $callback, 'priority' => $priority];
}

function do_action($hook_name, $args = []) {
    global $rdys_hooks;
    if (isset($rdys_hooks[$hook_name])) {
        // مرتب‌سازی بر اساس اولویت
        usort($rdys_hooks[$hook_name], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        foreach ($rdys_hooks[$hook_name] as $hook) {
            call_user_func_array($hook['callback'], $args);
        }
    }
}

// -----------------------------------------------------------------------------
// 7. Database Connection (اتصال به دیتابیس)
// -----------------------------------------------------------------------------
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // امنیت بالاتر در برابر SQL Injection
        PDO::ATTR_PERSISTENT         => false, // اتصال غیرپایدار برای مدیریت بهتر منابع
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // صفحه خطای دیتابیس زیبا (بدون لو دادن اطلاعات حساس)
    http_response_code(503);
    die('
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8"><title>خطای سرویس</title>
        <style>
            body{background:#010101;color:#fff;font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}
            .card{background:#0D0D0D;border:1px solid #1F1F1F;padding:2rem;border-radius:1rem;text-align:center;max-width:400px;box-shadow:0 10px 30px rgba(0,0,0,0.5);}
            h1{color:#FF3B30;font-size:1.5rem;margin-bottom:1rem;}
            p{color:#9CA3AF;line-height:1.6;font-size:0.9rem;}
            code{display:block;background:#1a1a1a;padding:10px;border-radius:5px;margin:15px 0;color:#e5e7eb;font-family:monospace;direction:ltr;text-align:left;overflow-x:auto;}
            .btn{background:#00B0A4;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;display:inline-block;margin-top:15px;font-weight:bold;}
        </style>
    </head>
    <body>
        <div class="card">
            <h1>⛔ خطای اتصال پایگاه داده</h1>
            <p>ارتباط با سرور برقرار نشد. لطفا تنظیمات فایل <code>config.php</code> را بررسی کنید.</p>
            ' . (DEBUG_MODE ? '<code>' . $e->getMessage() . '</code>' : '') . '
            <a href="install.php" class="btn">بررسی نصب‌کننده</a>
        </div>
    </body>
    </html>
    ');
}

// تابع کمکی برای آدرس‌دهی
function site_url($path = '') {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}
?>