<?php
/**
 * RDYS - Enterprise API Endpoint
 * Theme: Ready Studio (Teal & Black)
 * Version: 10.0.0 (Maximized)
 * * Logic: Handles all AJAX/REST requests for Link creation, Domain management, and Analytics.
 * * Security: Dual Auth (Session/Token), CORS Support, Input Validation.
 */

// لود کردن تنظیمات و توابع
require_once 'functions.php';

// تنظیم هدرهای پاسخ (JSON)
header('Content-Type: application/json; charset=utf-8');

// تنظیمات CORS (در محیط پروداکشن باید محدود شود)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400'); // کش کردن تنظیمات CORS برای یک روز

// هندل کردن درخواست‌های Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// فقط متد POST مجاز است
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد درخواست نامعتبر است.']);
    exit;
}

// =================================================================================
// 1. AUTHENTICATION (احراز هویت هوشمند)
// =================================================================================

$currentUser = null;

// الف) احراز هویت با سشن (برای داشبورد و فرانت‌اند)
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
} 
// ب) احراز هویت با توکن (برای API و ربات‌ها)
else {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, p.name as plan_name, p.link_limit, p.domain_limit, p.has_api 
                FROM users u 
                LEFT JOIN plans p ON u.plan_id = p.id 
                WHERE u.api_token = ? LIMIT 1
            ");
            $stmt->execute([$token]);
            $apiUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($apiUser) {
                // بررسی دسترسی API در پلن کاربر
                if (!$apiUser['has_api'] && $apiUser['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'پلن شما دسترسی API ندارد.']);
                    exit;
                }
                $currentUser = $apiUser;
            }
        } catch (Exception $e) {
            // خطای خاموش
        }
    }
}

// دریافت اکشن
$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'درخواست نامعتبر'];

try {
    switch ($action) {
        
        // =========================================================
        // 2. SHORTEN LINK (ساخت لینک کوتاه)
        // =========================================================
        case 'shorten':
            $url = trim($_POST['url'] ?? '');
            $alias = trim($_POST['alias'] ?? '');
            $domainId = intval($_POST['domain_id'] ?? 1);
            
            // احراز هویت الزامی است
            if (!$currentUser) {
                http_response_code(401);
                throw new Exception('برای ساخت لینک لطفا وارد حساب خود شوید.');
            }

            // فراخوانی تابع هسته برای ساخت لینک
            $result = createShortLink($url, $alias, $domainId, $currentUser['id']);
            
            if ($result['success']) {
                $response = [
                    'success' => true,
                    'message' => 'لینک با موفقیت ساخته شد.',
                    'data' => [
                        'short_url' => $result['url'],
                        'short_code' => $result['code'],
                        'original_url' => $url,
                        'id' => $result['id']
                    ]
                ];
            } else {
                $response = ['success' => false, 'message' => $result['message']];
            }
            break;

        // =========================================================
        // 3. DELETE LINK (حذف لینک)
        // =========================================================
        case 'delete':
            if (!$currentUser) throw new Exception('دسترسی غیرمجاز');

            $linkId = intval($_POST['id'] ?? 0);
            if ($linkId <= 0) throw new Exception('شناسه لینک نامعتبر است.');

            // بررسی مالکیت (ادمین همه را می‌تواند حذف کند)
            if ($currentUser['role'] === 'admin') {
                $stmt = $pdo->prepare("SELECT id FROM links WHERE id = ?");
                $stmt->execute([$linkId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM links WHERE id = ? AND user_id = ?");
                $stmt->execute([$linkId, $currentUser['id']]);
            }

            if (!$stmt->fetch()) throw new Exception('لینک یافت نشد یا شما اجازه حذف آن را ندارید.');

            $pdo->prepare("DELETE FROM links WHERE id = ?")->execute([$linkId]);
            $response = ['success' => true, 'message' => 'لینک با موفقیت حذف شد.'];
            break;

        // =========================================================
        // 4. GET STATS (دریافت آمار برای نمودار)
        // =========================================================
        case 'get_stats':
            if (!$currentUser) throw new Exception('دسترسی غیرمجاز');

            $linkId = intval($_POST['id'] ?? 0);

            // بررسی مالکیت
            $checkStmt = $pdo->prepare("SELECT id FROM links WHERE id = ? AND (user_id = ? OR ? = 'admin')");
            $checkStmt->execute([$linkId, $currentUser['id'], $currentUser['role']]);
            if (!$checkStmt->fetch()) throw new Exception('دسترسی به آمار این لینک امکان‌پذیر نیست.');

            // الف) آمار بازدید ۷ روز اخیر
            $stmt = $pdo->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM stats 
                WHERE link_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$linkId]);
            $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // پر کردن روزهای خالی (Gap Filling)
            $chartData = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $c = 0;
                foreach ($dailyStats as $stat) {
                    if ($stat['date'] === $d) { $c = (int)$stat['count']; break; }
                }
                $chartData[] = ['date' => $d, 'count' => $c];
            }

            // ب) آمار مرورگرها
            $stmt = $pdo->prepare("
                SELECT user_agent, COUNT(*) as cnt 
                FROM stats 
                WHERE link_id = ? 
                GROUP BY user_agent 
                ORDER BY cnt DESC 
                LIMIT 100
            ");
            $stmt->execute([$linkId]);
            $rawUA = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // تحلیل ساده User-Agent
            $browsers = ['Chrome' => 0, 'Firefox' => 0, 'Safari' => 0, 'Edge' => 0, 'Other' => 0];
            foreach($rawUA as $row) {
                $agent = $row['user_agent'];
                if (strpos($agent, 'Edg') !== false) $browsers['Edge'] += $row['cnt'];
                elseif (strpos($agent, 'Chrome') !== false) $browsers['Chrome'] += $row['cnt'];
                elseif (strpos($agent, 'Firefox') !== false) $browsers['Firefox'] += $row['cnt'];
                elseif (strpos($agent, 'Safari') !== false) $browsers['Safari'] += $row['cnt'];
                else $browsers['Other'] += $row['cnt'];
            }
            
            // حذف مقادیر صفر برای تمیزی خروجی
            $browsers = array_filter($browsers, function($v) { return $v > 0; });
            if (empty($browsers)) $browsers = ['No Data' => 0];

            $response = [
                'success' => true, 
                'daily' => $chartData, 
                'browsers' => $browsers
            ];
            break;

        // =========================================================
        // 5. ADD DOMAIN (افزودن دامنه)
        // =========================================================
        case 'add_domain':
            if (!$currentUser) throw new Exception('دسترسی غیرمجاز');

            $domain = strtolower(trim($_POST['domain'] ?? ''));
            // حذف پروتکل و اسلش‌های اضافی
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');

            // اعتبارسنجی نام دامنه
            if (empty($domain) || !preg_match('/^(?:[-A-Za-z0-9]+\.)+[A-Za-z]{2,10}$/', $domain)) {
                throw new Exception('فرمت نام دامنه معتبر نیست.');
            }
            
            // بررسی محدودیت پلن (برای کاربران غیر ادمین)
            if ($currentUser['role'] !== 'admin') {
                $canAdd = canAddDomain($currentUser['id']);
                if ($canAdd !== true) {
                    throw new Exception($canAdd);
                }
            }

            // بررسی تکراری بودن دامنه در کل سیستم
            $checkStmt = $pdo->prepare("SELECT id FROM domains WHERE domain = ?");
            $checkStmt->execute([$domain]);
            if ($checkStmt->fetch()) throw new Exception('این دامنه قبلا در سیستم ثبت شده است.');

            // درج دامنه
            $stmt = $pdo->prepare("INSERT INTO domains (user_id, domain, is_active, is_verified) VALUES (?, ?, 1, 0)");
            if ($stmt->execute([$currentUser['id'], $domain])) {
                $response = [
                    'success' => true, 
                    'message' => 'دامنه با موفقیت اضافه شد. لطفا DNS دامنه را تنظیم کنید.'
                ];
            } else {
                throw new Exception('خطا در ثبت دامنه.');
            }
            break;

        // =========================================================
        // 6. TOGGLE DOMAIN (تغییر وضعیت دامنه)
        // =========================================================
        case 'toggle_domain':
            if (!$currentUser) throw new Exception('دسترسی غیرمجاز');
            
            $domainId = intval($_POST['id']);
            
            // فقط مالک دامنه یا ادمین می‌تواند وضعیت را تغییر دهد
            $sql = "UPDATE domains SET is_active = NOT is_active WHERE id = ? AND (user_id = ? OR ? = 'admin')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$domainId, $currentUser['id'], $currentUser['role']]);
            
            if ($stmt->rowCount()) {
                $response = ['success' => true, 'message' => 'وضعیت دامنه تغییر کرد.'];
            } else {
                throw new Exception('عملیات ناموفق بود. یا دامنه وجود ندارد یا دسترسی ندارید.');
            }
            break;

        // =========================================================
        // 7. REGENERATE TOKEN (تولید مجدد توکن)
        // =========================================================
        case 'regenerate_token':
            if (!$currentUser) throw new Exception('دسترسی غیرمجاز');
            
            $newToken = generateApiToken();
            $stmt = $pdo->prepare("UPDATE users SET api_token = ? WHERE id = ?");
            if ($stmt->execute([$newToken, $currentUser['id']])) {
                $response = [
                    'success' => true, 
                    'token' => $newToken, 
                    'message' => 'توکن API جدید با موفقیت ایجاد شد.'
                ];
            } else {
                throw new Exception('خطا در بروزرسانی توکن.');
            }
            break;

        default:
            http_response_code(400);
            throw new Exception('عملیات درخواستی نامعتبر است.');
    }

} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
    // تنظیم کد وضعیت بر اساس نوع خطا (اگر مشخص شده باشد)
    $code = $e->getCode();
    if ($code >= 400 && $code < 600) http_response_code($code);
    else http_response_code(400);

} catch (PDOException $e) {
    error_log("API Database Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'خطای داخلی پایگاه داده.'];
    http_response_code(500);
}

echo json_encode($response);
exit;
?>