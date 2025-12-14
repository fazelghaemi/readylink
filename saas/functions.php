<?php
/**
 * RDYS - Enterprise Core Functions
 * Theme: Ready Studio (Teal & Black)
 * Version: 10.0.0 (Maximized)
 * * This file contains the business logic for the SaaS platform.
 * * It handles User Auth, Plan Quotas, Link Generation, and Domain Logic.
 */

// اطمینان از لود شدن فایل کانفیگ
require_once 'config.php';

// =================================================================================
// 1. AUTHENTICATION & USER MANAGEMENT (مدیریت کاربران)
// =================================================================================

/**
 * بررسی اینکه آیا کاربر لاگین است یا خیر
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * دریافت اطلاعات کامل کاربر جاری به همراه جزئیات پلن اشتراکی
 * @return array|false اطلاعات کاربر یا false در صورت عدم ورود
 */
function getCurrentUser() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }

    try {
        // اتصال (JOIN) به جدول پلن‌ها برای دریافت محدودیت‌ها در لحظه
        $stmt = $pdo->prepare("
            SELECT 
                u.*, 
                p.name as plan_name, 
                p.link_limit, 
                p.domain_limit, 
                p.has_api 
            FROM users u 
            LEFT JOIN plans p ON u.plan_id = p.id 
            WHERE u.id = ? 
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            // حذف پسورد از خروجی برای امنیت بیشتر
            unset($user['password']);
            return $user;
        }
        
        // اگر سشن هست اما کاربر در دیتابیس نیست (مثلا حذف شده)
        session_destroy();
        return false;

    } catch (PDOException $e) {
        error_log("Auth Error: " . $e->getMessage());
        return false;
    }
}

/**
 * بررسی اینکه آیا کاربر فعلی مدیر کل (Super Admin) است؟
 * @return bool
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * تولید توکن API امن و یکتا
 * @return string
 */
function generateApiToken() {
    try {
        return bin2hex(random_bytes(32)); // 64 کاراکتر هگزادسیمال
    } catch (Exception $e) {
        // فال‌بک در صورت عدم پشتیبانی سیستم عامل
        return md5(uniqid(rand(), true) . time());
    }
}

// =================================================================================
// 2. PLAN & QUOTA SYSTEM (مدیریت محدودیت‌ها و پلن‌ها)
// =================================================================================

/**
 * بررسی اینکه آیا کاربر مجاز به ساخت لینک جدید است؟
 * @param int $userId شناسه کاربر
 * @return bool|string true اگر مجاز است، متن خطا اگر غیرمجاز است
 */
function canCreateLink($userId) {
    global $pdo;
    
    // دریافت اطلاعات کاربر و پلن
    // به جای کوئری سنگین، می‌توانیم اگر کاربر جاری است از سشن استفاده کنیم
    // اما برای دقت بالا (Real-time) دوباره چک می‌کنیم.
    $stmt = $pdo->prepare("
        SELECT p.link_limit, u.role 
        FROM users u 
        LEFT JOIN plans p ON u.plan_id = p.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $data = $stmt->fetch();

    if (!$data) return "کاربر نامعتبر است.";

    // ادمین محدودیت ندارد
    if ($data['role'] === 'admin') return true;

    // شمارش لینک‌های ساخته شده توسط کاربر
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $currentLinks = $countStmt->fetchColumn();

    if ($currentLinks >= $data['link_limit']) {
        return "شما به سقف مجاز لینک‌های پلن خود ({$data['link_limit']} عدد) رسیده‌اید. لطفا حساب خود را ارتقا دهید.";
    }

    return true;
}

/**
 * بررسی اینکه آیا کاربر مجاز به افزودن دامنه جدید است؟
 * @param int $userId
 * @return bool|string
 */
function canAddDomain($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.domain_limit, u.role 
        FROM users u 
        LEFT JOIN plans p ON u.plan_id = p.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $data = $stmt->fetch();

    if (!$data) return "خطای کاربری.";
    if ($data['role'] === 'admin') return true;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM domains WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $currentDomains = $countStmt->fetchColumn();

    if ($currentDomains >= $data['domain_limit']) {
        return "شما به سقف مجاز دامنه‌های پلن خود ({$data['domain_limit']} عدد) رسیده‌اید.";
    }

    return true;
}

/**
 * دریافت لیست تمام دامنه‌های قابل استفاده برای یک کاربر
 * شامل: دامنه‌های عمومی سیستم + دامنه‌های اختصاصی و تایید شده کاربر
 * @param int|null $userId
 * @return array
 */
function getUserDomains($userId = null) {
    global $pdo;
    
    // دامنه‌های عمومی (فرض: متعلق به ادمین با ID=1 هستند و فعالند)
    // نکته: user_id=1 معمولاً رزرو شده برای ادمین اصلی است
    $publicQuery = "SELECT id, domain, 'public' as type FROM domains WHERE user_id = 1 AND is_active = 1";
    
    if ($userId) {
        // ترکیب با دامنه‌های شخصی کاربر (که فعال و تایید شده باشند)
        $sql = "($publicQuery) UNION ALL (SELECT id, domain, 'private' as type FROM domains WHERE user_id = ? AND is_active = 1 AND is_verified = 1) ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
    } else {
        // فقط عمومی‌ها برای کاربران مهمان
        $stmt = $pdo->query($publicQuery);
    }
    
    return $stmt->fetchAll();
}

// =================================================================================
// 3. CORE LINK LOGIC (منطق اصلی لینک‌ها)
// =================================================================================

/**
 * ساخت لینک کوتاه جدید با تمام بررسی‌های امنیتی و تجاری
 * @param string $longUrl لینک اصلی
 * @param string|null $customAlias نام مستعار دلخواه (اختیاری)
 * @param int $domainId شناسه دامنه
 * @param int $userId شناسه کاربر سازنده
 * @return array ['success'=>bool, 'message'=>string, 'data'=>array]
 */
function createShortLink($longUrl, $customAlias, $domainId, $userId) {
    global $pdo;

    // 1. نرمال‌سازی لینک (اضافه کردن http اگر ندارد)
    if (!preg_match("~^(?:f|ht)tps?://~i", $longUrl)) {
        $longUrl = "http://" . $longUrl;
    }

    // 2. اعتبارسنجی فرمت URL
    if (!filter_var($longUrl, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'message' => 'آدرس وارد شده معتبر نیست.'];
    }

    // 3. بررسی اسپم (Blacklist Check)
    if (isSpamDomain($longUrl)) {
        return ['success' => false, 'message' => 'دامنه مقصد در لیست سیاه سیستم قرار دارد.'];
    }

    // 4. بررسی محدودیت پلن کاربر
    $quotaCheck = canCreateLink($userId);
    if ($quotaCheck !== true) {
        return ['success' => false, 'message' => $quotaCheck];
    }

    // 5. اعتبارسنجی و انتخاب دامنه
    // کاربر فقط می‌تواند از دامنه‌های مجاز (عمومی یا مال خودش) استفاده کند
    $domainStmt = $pdo->prepare("SELECT domain FROM domains WHERE id = ? AND (user_id = 1 OR user_id = ?) AND is_active = 1 LIMIT 1");
    $domainStmt->execute([$domainId, $userId]);
    $domainName = $domainStmt->fetchColumn();

    if (!$domainName) {
        // اگر دامنه نامعتبر بود، فال‌بک به دامنه پیش‌فرض سیستم (اولین دامنه عمومی)
        $fallback = $pdo->query("SELECT id, domain FROM domains WHERE user_id = 1 AND is_active = 1 LIMIT 1")->fetch();
        if ($fallback) {
            $domainId = $fallback['id'];
            $domainName = $fallback['domain'];
        } else {
            return ['success' => false, 'message' => 'هیچ دامنه فعالی در سیستم یافت نشد.'];
        }
    }

    // 6. تولید یا بررسی کد کوتاه (Alias)
    $shortCode = '';
    
    if (!empty($customAlias)) {
        // پاکسازی نام مستعار (فقط حروف، اعداد، خط تیره و زیرخط)
        $customAlias = preg_replace('/[^a-zA-Z0-9-_]/', '', $customAlias);
        
        if (strlen($customAlias) < 3) {
            return ['success' => false, 'message' => 'نام مستعار باید حداقل ۳ کاراکتر باشد.'];
        }

        // بررسی یکتایی در کل سیستم (Alias باید گلوبال یونیک باشد)
        $check = $pdo->prepare("SELECT id FROM links WHERE short_code = ?");
        $check->execute([$customAlias]);
        if ($check->fetch()) {
            return ['success' => false, 'message' => 'این نام مستعار قبلاً استفاده شده است.'];
        }
        $shortCode = $customAlias;
    } else {
        // تولید خودکار کد یونیک با مکانیزم Retry
        $attempts = 0;
        $maxAttempts = 5;
        do {
            $shortCode = generateRandomCode(5); // طول پیش‌فرض 5
            $check = $pdo->prepare("SELECT id FROM links WHERE short_code = ?");
            $check->execute([$shortCode]);
            $exists = $check->fetch();
            $attempts++;
        } while ($exists && $attempts < $maxAttempts);

        if ($exists) {
            return ['success' => false, 'message' => 'خطا در تولید کد یکتا. لطفا مجدد تلاش کنید.'];
        }
    }

    // 7. درج نهایی در دیتابیس
    try {
        $stmt = $pdo->prepare("
            INSERT INTO links (user_id, domain_id, long_url, short_code, created_at, views, is_public) 
            VALUES (?, ?, ?, ?, NOW(), 0, 1)
        ");
        $stmt->execute([$userId, $domainId, $longUrl, $shortCode]);
        
        // ساخت آدرس کامل برای نمایش به کاربر
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $fullUrl = $protocol . $domainName . '/' . $shortCode;

        return [
            'success' => true,
            'url' => $fullUrl,
            'code' => $shortCode,
            'id' => $pdo->lastInsertId()
        ];

    } catch (PDOException $e) {
        error_log("DB Insert Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'خطای دیتابیس هنگام ذخیره لینک.'];
    }
}

// =================================================================================
// 4. UTILITY FUNCTIONS (ابزارهای کمکی)
// =================================================================================

/**
 * تولید رشته تصادفی امن
 */
function generateRandomCode($length = 5) {
    // کاراکترهای خوانا (حذف l, 1, o, 0 برای جلوگیری از اشتباه دیداری)
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $result = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[random_int(0, $max)]; // random_int امن‌تر از rand است
    }
    return $result;
}

/**
 * پاکسازی ورودی‌های کاربر (XSS Protection)
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * دریافت آی‌پی واقعی کاربر (پشتیبانی از کلودفلر و پروکسی)
 */
function getClientIP() {
    // اولویت با هدرهای کلودفلر یا فوروارد شده است
    $headers = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                // اعتبارسنجی IP که معتبر باشد و در رنج لوکال نباشد
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * تبدیل تاریخ به فرمت "زمان سپری شده" (Time Ago)
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'لحظاتی پیش';
    
    $minutes = round($diff / 60);
    if ($minutes < 60) return $minutes . ' دقیقه پیش';
    
    $hours = round($diff / 3600);
    if ($hours < 24) return $hours . ' ساعت پیش';
    
    $days = round($diff / 86400);
    if ($days < 30) return $days . ' روز پیش';
    
    return date("Y/m/d", $time); // برای قدیمی‌ترها تاریخ دقیق
}

/**
 * بررسی لیست سیاه دامنه‌ها (Anti-Spam)
 */
function isSpamDomain($url) {
    $blacklist = [
        'bit.ly', 'goo.gl', 'tinyurl.com', // جلوگیری از کوتاه کردن مجدد
        'porn.com', 'sex.com', // محتوای نامناسب
        'malware.site', 'virus.com' // بدافزارها
    ];
    
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    
    foreach ($blacklist as $bad) {
        if (stripos($host, $bad) !== false) return true;
    }
    return false;
}
?>