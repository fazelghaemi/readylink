<?php
/**
 * RDYS - Core Logic & Helper Functions
 * * @author Senior Developer
 * @version 2.0.0
 * * این فایل حاوی تمام توابع منطقی سیستم است.
 */

// اطمینان از لود شدن فایل کانفیگ
require_once 'config.php';

// -----------------------------------------------------------------------------
// 1. توابع احراز هویت و امنیت (Auth & Security)
// -----------------------------------------------------------------------------

/**
 * بررسی اینکه آیا کاربر مدیر است یا خیر
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['role']) && 
           $_SESSION['role'] === 'admin';
}

/**
 * پاکسازی ورودی‌های کاربر برای جلوگیری از XSS
 * @param string $data
 * @return string
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * دریافت آی‌پی واقعی کاربر (حتی پشت Cloudflare یا Proxy)
 * @return string
 */
function getClientIP() {
    $ipKeys = [
        'HTTP_CLIENT_IP', 
        'HTTP_X_FORWARDED_FOR', 
        'HTTP_X_FORWARDED', 
        'HTTP_X_CLUSTER_CLIENT_IP', 
        'HTTP_FORWARDED_FOR', 
        'HTTP_FORWARDED', 
        'REMOTE_ADDR'
    ];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                // اعتبارسنجی IP که لوکال یا پرایوت نباشد (اختیاری)
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// -----------------------------------------------------------------------------
// 2. توابع اصلی کوتاه‌کننده (Core Shortener Logic)
// -----------------------------------------------------------------------------

/**
 * تولید یک کد تصادفی یونیک
 * @param int $length طول کد
 * @return string
 */
function generateRandomCode($length = 5) {
    // کاراکترهای مجاز (بدون کاراکترهای گیج کننده مثل l, 1, o, 0)
    $characters = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * بررسی اسپم بودن دامنه
 * @param string $url
 * @return bool
 */
function isSpamDomain($url) {
    $blacklist = [
        'porn.com', 'malware.site', 'phishing.xyz', // لیست سیاه خود را اینجا پر کنید
        'bit.ly', 'goo.gl', 't.co' // جلوگیری از کوتاه کردن مجدد لینک‌های کوتاه
    ];
    
    $parsedHost = parse_url($url, PHP_URL_HOST);
    
    // هوک برای افزونه‌های ضد اسپم خارجی
    do_action('check_spam_filter', [$url]);

    if (!$parsedHost) return false;

    foreach ($blacklist as $badDomain) {
        if (stripos($parsedHost, $badDomain) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * ساخت لینک کوتاه جدید
 * @param string $longUrl لینک اصلی
 * @param string|null $customAlias نام مستعار (اختیاری)
 * @param int $domainId شناسه دامنه انتخابی
 * @return array ['success' => bool, 'message' => string, 'url' => string]
 */
function createShortLink($longUrl, $customAlias = null, $domainId = 1) {
    global $pdo;

    // 1. اعتبارسنجی لینک
    if (!filter_var($longUrl, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'message' => 'لینک وارد شده معتبر نیست.'];
    }

    // 2. بررسی اسپم
    if (isSpamDomain($longUrl)) {
        return ['success' => false, 'message' => 'این دامنه در لیست سیاه قرار دارد.'];
    }

    // 3. تعیین کد کوتاه (Alias)
    $shortCode = '';
    
    if (!empty($customAlias)) {
        // اگر کاربر نام مستعار وارد کرده، فقط حروف و اعداد و خط تیره مجاز است
        $customAlias = preg_replace('/[^a-zA-Z0-9-_]/', '', $customAlias);
        
        // چک کردن تکراری نبودن
        $stmt = $pdo->prepare("SELECT id FROM links WHERE short_code = ?");
        $stmt->execute([$customAlias]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'این نام مستعار قبلاً استفاده شده است.'];
        }
        $shortCode = $customAlias;
    } else {
        // تولید کد یونیک (با مکانیزم تلاش مجدد در صورت تکراری بودن)
        $maxTries = 5;
        $foundUnique = false;
        
        for ($i = 0; $i < $maxTries; $i++) {
            $tempCode = generateRandomCode(5); // طول پیش‌فرض 5
            $stmt = $pdo->prepare("SELECT id FROM links WHERE short_code = ?");
            $stmt->execute([$tempCode]);
            if (!$stmt->fetch()) {
                $shortCode = $tempCode;
                $foundUnique = true;
                break;
            }
        }
        
        if (!$foundUnique) {
            return ['success' => false, 'message' => 'سیستم شلوغ است، لطفا مجدد تلاش کنید.'];
        }
    }

    // 4. درج در دیتابیس
    try {
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        $stmt = $pdo->prepare("INSERT INTO links (user_id, domain_id, long_url, short_code, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $domainId, $longUrl, $shortCode]);
        
        // ساخت آدرس نهایی
        $domainStmt = $pdo->prepare("SELECT domain FROM domains WHERE id = ?");
        $domainStmt->execute([$domainId]);
        $domainName = $domainStmt->fetchColumn();
        
        // فال‌بک اگر دامنه پیدا نشد (نباید اتفاق بیفتد)
        if (!$domainName) $domainName = $_SERVER['HTTP_HOST'];

        // پروتکل (http/https)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        
        $finalUrl = $protocol . $domainName . '/' . $shortCode;

        // اجرای هوک بعد از ساخت لینک (مثلا برای لاگ یا ایمیل)
        do_action('after_link_created', [$shortCode, $longUrl]);

        return [
            'success' => true,
            'url' => $finalUrl,
            'code' => $shortCode
        ];

    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            return ['success' => false, 'message' => 'Database Error: ' . $e->getMessage()];
        }
        return ['success' => false, 'message' => 'خطای داخلی سرور رخ داد.'];
    }
}

// -----------------------------------------------------------------------------
// 3. توابع آمار و داشبورد (Analytics & Utils)
// -----------------------------------------------------------------------------

/**
 * تبدیل تاریخ به فرمت "X دقیقه پیش" (Time Ago)
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) return 'همین الان';
    if ($diff < 3600) return floor($diff / 60) . ' دقیقه پیش';
    if ($diff < 86400) return floor($diff / 3600) . ' ساعت پیش';
    if ($diff < 604800) return floor($diff / 86400) . ' روز پیش';
    
    return date("Y/m/d", $time);
}

/**
 * دریافت اطلاعات مرورگر و سیستم عامل (ساده)
 * @param string $userAgent
 * @return array ['browser', 'os']
 */
function parseUserAgent($userAgent) {
    $browser = "Unknown";
    $os = "Unknown";
    
    // تشخیص سیستم عامل
    if (preg_match('/windows|win32/i', $userAgent)) $os = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $userAgent)) $os = 'Mac OS';
    elseif (preg_match('/linux/i', $userAgent)) $os = 'Linux';
    elseif (preg_match('/android/i', $userAgent)) $os = 'Android';
    elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) $os = 'iOS';

    // تشخیص مرورگر
    if (preg_match('/MSIE/i', $userAgent) && !preg_match('/Opera/i', $userAgent)) $browser = 'IE';
    elseif (preg_match('/Firefox/i', $userAgent)) $browser = 'Firefox';
    elseif (preg_match('/Chrome/i', $userAgent)) $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $userAgent)) $browser = 'Safari';
    elseif (preg_match('/Opera/i', $userAgent)) $browser = 'Opera';
    elseif (preg_match('/Edge/i', $userAgent)) $browser = 'Edge';

    return ['browser' => $browser, 'os' => $os];
}
?>