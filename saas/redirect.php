<?php
/**
 * RDYS - Enterprise Redirection Engine
 * Theme: Ready Studio (Teal & Black)
 * Version: 10.0.0 (Maximized)
 * * Logic: Domain-aware routing, Deep Analytics, Bot Filtering, Parameter Forwarding.
 * * Performance: Optimized for high-concurrency.
 */

// لود کردن کانفیگ و توابع (فقط توابع ضروری برای سرعت)
require_once 'functions.php';

// تنظیم هدرهای امنیتی و کش
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// =================================================================================
// 1. INPUT PROCESSING (پردازش ورودی)
// =================================================================================

// دریافت کد کوتاه
$code = $_GET['code'] ?? '';

// فال‌بک هوشمند: استخراج از URL اگر پارامتر خالی بود
if (empty($code)) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // حذف اسلش‌های اضافی و مسیر پوشه نصب (اگر در پوشه باشد)
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    if ($scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
        $uri = substr($uri, strlen($scriptDir));
    }
    $code = trim($uri, '/');
}

// پاکسازی کد
$code = str_replace(['.php', 'index'], '', $code);
$code = preg_replace('/[^a-zA-Z0-9-_]/', '', $code);

// لیست فایل‌های سیستمی (White-list Routing)
$systemFiles = ['admin', 'login', 'register', 'api', 'install', 'redirect', '404', 'favicon', 'robots', 'sitemap', 'assets', 'recovery'];

// اگر کد نامعتبر یا سیستمی است، به صفحه اصلی برو
if (empty($code) || in_array($code, $systemFiles)) {
    // جلوگیری از لوپ ریدارکت
    if (basename($_SERVER['PHP_SELF']) == 'redirect.php') {
        header("Location: index.php");
        exit;
    }
    // اگر فایل اینکلود شده باشد، کاری نکن (ادامه اجرای اسکریپت اصلی)
    return; 
}

// =================================================================================
// 2. DOMAIN & LINK LOOKUP (جستجو در دیتابیس)
// =================================================================================

try {
    // تشخیص دامنه درخواست شده (Host Header)
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/^www\./', '', $host);

    // جستجوی لینک با شرط دامنه
    // این کوئری چک می‌کند که آیا لینک کوتاه روی این دامنه خاص وجود دارد یا خیر
    // (در سیستم SaaS، یک کد کوتاه ممکن است روی دامنه‌های مختلف تکرار شود، پس ترکیب domain_id مهم است)
    $stmt = $pdo->prepare("
        SELECT 
            l.id, l.long_url, l.views, d.domain 
        FROM links l
        LEFT JOIN domains d ON l.domain_id = d.id
        WHERE BINARY l.short_code = :code 
        AND (d.domain = :host OR d.domain IS NULL) -- پشتیبانی از دامنه پیش‌فرض اگر NULL باشد
        LIMIT 1
    ");
    
    $stmt->execute([':code' => $code, ':host' => $host]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    // اگر دقیقاً پیدا نشد، شاید روی دامنه پیش‌فرض سیستم باشد (Fallback)
    if (!$link) {
        // دریافت دامنه پیش‌فرض (ID=1 یا اولین دامنه ادمین)
        $defaultDomainStmt = $pdo->query("SELECT domain FROM domains WHERE id = 1 LIMIT 1");
        $defaultDomain = $defaultDomainStmt->fetchColumn();
        
        if ($host === $defaultDomain) {
            // جستجوی مجدد بدون شرط دامنه (برای لینک‌های قدیمی یا سراسری)
            $stmt = $pdo->prepare("SELECT id, long_url, views FROM links WHERE BINARY short_code = ? LIMIT 1");
            $stmt->execute([$code]);
            $link = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if ($link) {
        $longUrl = $link['long_url'];

        // =================================================================================
        // 3. PARAMETER FORWARDING (انتقال پارامترها)
        // =================================================================================
        // حفظ UTM Tags و سایر پارامترها
        if (!empty($_GET)) {
            $queryParams = $_GET;
            unset($queryParams['code']); // حذف پارامتر داخلی خودمان
            
            if (!empty($queryParams)) {
                $separator = (parse_url($longUrl, PHP_URL_QUERY) == NULL) ? '?' : '&';
                $longUrl .= $separator . http_build_query($queryParams);
            }
        }

        // =================================================================================
        // 4. ANALYTICS & LOGGING (ثبت آمار)
        // =================================================================================
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // تشخیص ربات‌ها (Bot Filter)
        $isBot = false;
        if (preg_match('/bot|crawl|slurp|spider|mediapartners|facebookexternalhit/i', $userAgent)) {
            $isBot = true;
        }

        // فقط بازدیدهای واقعی را ثبت کن
        if (!$isBot) {
            $ip = getClientIP();
            $referrer = $_SERVER['HTTP_REFERER'] ?? '';

            // ثبت در جدول stats (Fire and Forget)
            $logStmt = $pdo->prepare("INSERT INTO stats (link_id, ip_address, referrer, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
            $logStmt->execute([$link['id'], $ip, $referrer, $userAgent]);
            
            // آپدیت شمارنده سریع
            $updateStmt = $pdo->prepare("UPDATE links SET views = views + 1 WHERE id = ?");
            $updateStmt->execute([$link['id']]);
        }

        // =================================================================================
        // 5. REDIRECTION (هدایت)
        // =================================================================================

        // هدایت ۳۰۱ (دائمی)
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: " . $longUrl);
        exit;

    } else {
        // =================================================================================
        // 6. ERROR HANDLING (لینک یافت نشد)
        // =================================================================================
        http_response_code(404);
        
        if (file_exists('404.php')) {
            include '404.php';
        } else {
            // Fallback UI
            echo '<!DOCTYPE html><html dir="rtl"><body style="background:#010101;color:#fff;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;">';
            echo '<div style="text-align:center;">';
            echo '<h1 style="color:#00B0A4;font-size:3em;margin:0;">404</h1>';
            echo '<p>لینک مورد نظر یافت نشد.</p>';
            echo '<a href="index.php" style="color:#9CA3AF;">بازگشت به خانه</a>';
            echo '</div></body></html>';
        }
        exit;
    }

} catch (Exception $e) {
    // لاگ خطای سیستمی
    error_log("[RDYS Redirect Error] " . $e->getMessage());
    header("Location: index.php");
    exit;
}
?>