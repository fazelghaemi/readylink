<?php
/**
 * RDYS - Ultimate Redirection Engine
 * Theme: Ready Studio (Teal & Black)
 * Version: 5.0.0 (Enterprise Grade)
 * * Features:
 * - Smart URI Parsing (Even without .htaccess)
 * - Query Parameter Forwarding (Preserves UTM tags)
 * - Bot Detection & Filtering
 * - Advanced Security Headers
 * - Real-time Analytics Logging
 */

// تنظیمات اولیه و اتصال به هسته
require_once 'functions.php';

// تنظیم هدرهای امنیتی پیش از هر کاری
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
// اگر SSL دارید، HSTS را فعال کنید (برای امنیت بیشتر)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

// =========================================================
// 1. استخراج هوشمند کد کوتاه (Smart Code Extraction)
// =========================================================

$code = $_GET['code'] ?? '';

// اگر پارامتر code خالی بود، تلاش برای استخراج از URL
if (empty($code)) {
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $code = trim($requestUri, '/');
}

// پاکسازی‌های اولیه
$code = str_replace(['.php', 'index'], '', $code);
$code = preg_replace('/[^a-zA-Z0-9-_]/', '', $code);

// لیست فایل‌های سیستمی محافظت شده
$systemFiles = ['admin', 'login', 'api', 'install', 'redirect', '404', 'favicon', 'robots', 'sitemap', 'assets'];

// بررسی‌های امنیتی
if (empty($code) || in_array($code, $systemFiles)) {
    if (basename($_SERVER['PHP_SELF']) == 'redirect.php') {
        header("Location: index.php");
        exit;
    }
}

// =========================================================
// 2. جستجو در پایگاه داده (Database Lookup)
// =========================================================

try {
    // استفاده از BINARY برای حساسیت به حروف بزرگ و کوچک
    $stmt = $pdo->prepare("SELECT id, long_url, views FROM links WHERE BINARY short_code = ? LIMIT 1");
    $stmt->execute([$code]);
    $link = $stmt->fetch();

    if ($link) {
        $longUrl = $link['long_url'];
        
        // =========================================================
        // 3. انتقال پارامترهای URL (Query Forwarding)
        // =========================================================
        // اگر لینک کوتاه شامل ?utm_source=... باشد، آن را به مقصد اضافه می‌کند
        if (!empty($_GET)) {
            // حذف پارامتر code که مربوط به سیستم خودمان است
            $queryParams = $_GET;
            unset($queryParams['code']);
            
            if (!empty($queryParams)) {
                // بررسی اینکه آیا لینک اصلی خودش پارامتر دارد یا خیر
                $separator = (parse_url($longUrl, PHP_URL_QUERY) == NULL) ? '?' : '&';
                $longUrl .= $separator . http_build_query($queryParams);
            }
        }

        // =========================================================
        // 4. تحلیل بازدیدکننده و ثبت آمار (Advanced Analytics)
        // =========================================================
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // تشخیص ربات‌ها (برای جلوگیری از آمار فیک)
        $isBot = false;
        if (preg_match('/bot|crawl|slurp|spider|mediapartners/i', $userAgent)) {
            $isBot = true;
        }

        // فقط اگر ربات نیست، آمار را ثبت کن (یا می‌توانید با فلگ is_bot ثبت کنید)
        if (!$isBot) {
            $ip = getClientIP();
            $referrer = $_SERVER['HTTP_REFERER'] ?? '';

            // ثبت آمار در جدول stats (Fire and Forget)
            $logStmt = $pdo->prepare("INSERT INTO stats (link_id, ip_address, referrer, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
            $logStmt->execute([$link['id'], $ip, $referrer, $userAgent]);
            
            // آپدیت شمارنده بازدید کل
            $updateStmt = $pdo->prepare("UPDATE links SET views = views + 1 WHERE id = ?");
            $updateStmt->execute([$link['id']]);
        }

        // =========================================================
        // 5. هدایت نهایی (Final Redirection)
        // =========================================================

        // جلوگیری کامل از کش شدن توسط مرورگر (بسیار مهم برای آمار دقیق)
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

        // ریدارکت 301 (دائمی) بهترین گزینه برای سئو است
        // اما چون هدرهای no-cache داریم، مرورگر مجبور است هر بار چک کند
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: " . $longUrl);
        exit;

    } else {
        // لینک پیدا نشد
        http_response_code(404);
        if(file_exists('404.php')) {
            include '404.php';
        } else {
            die('404 - Link Not Found');
        }
        exit;
    }

} catch (Exception $e) {
    // لاگ کردن خطای سیستمی بدون نمایش به کاربر
    error_log("[RDYS Error] " . $e->getMessage());
    header("Location: index.php");
    exit;
}
?>