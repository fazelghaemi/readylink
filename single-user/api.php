<?php
/**
 * RDYS - API Endpoint Handler
 * * @author Senior Developer
 * @version 2.0.0
 * * این فایل تمام درخواست‌های AJAX (فرانت و پنل مدیریت) را پردازش می‌کند.
 */

require_once 'functions.php';

// تنظیم هدر برای پاسخ JSON
header('Content-Type: application/json; charset=utf-8');

// فقط متد POST مجاز است
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// دریافت اکشن مورد نظر
$action = $_POST['action'] ?? '';

// پاسخ استاندارد
$response = ['success' => false, 'message' => 'Invalid Request'];

try {
    switch ($action) {
        // ---------------------------------------------------------------------
        // 1. ساخت لینک کوتاه (Public)
        // ---------------------------------------------------------------------
        case 'shorten':
            // جلوگیری از ارسال رگباری درخواست (Simple Rate Limit)
            if (isset($_SESSION['last_req_time']) && (time() - $_SESSION['last_req_time'] < 2)) {
                throw new Exception('لطفا کمی صبر کنید و دوباره تلاش کنید.');
            }
            $_SESSION['last_req_time'] = time();

            $url = trim($_POST['url'] ?? '');
            $alias = trim($_POST['alias'] ?? '');
            $domainId = intval($_POST['domain_id'] ?? 1);

            // فراخوانی تابع منطقی از functions.php
            $result = createShortLink($url, $alias, $domainId);

            if ($result['success']) {
                $response = [
                    'success' => true,
                    'message' => 'لینک با موفقیت ساخته شد.',
                    'url'     => $result['url'],
                    'code'    => $result['code']
                ];
            } else {
                $response = ['success' => false, 'message' => $result['message']];
            }
            break;

        // ---------------------------------------------------------------------
        // 2. حذف لینک (Admin Only)
        // ---------------------------------------------------------------------
        case 'delete':
            if (!isAdmin()) {
                http_response_code(403);
                throw new Exception('دسترسی غیرمجاز.');
            }

            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('شناسه نامعتبر است.');

            $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
            $stmt->execute([$id]);

            $response = ['success' => true, 'message' => 'لینک حذف شد.'];
            break;

        // ---------------------------------------------------------------------
        // 3. دریافت آمار نمودار (Admin Only)
        // ---------------------------------------------------------------------
        case 'get_stats':
            if (!isAdmin()) {
                http_response_code(403);
                throw new Exception('دسترسی غیرمجاز.');
            }

            $linkId = intval($_POST['id'] ?? 0);
            
            // آمار ۷ روز اخیر
            $stmt = $pdo->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM stats 
                WHERE link_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$linkId]);
            $dailyStats = $stmt->fetchAll();

            // پر کردن روزهای خالی با صفر (برای زیبایی نمودار)
            $chartData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $count = 0;
                foreach ($dailyStats as $stat) {
                    if ($stat['date'] === $date) {
                        $count = (int)$stat['count'];
                        break;
                    }
                }
                $chartData[] = ['date' => $date, 'count' => $count];
            }

            // آمار مرورگرها
            $stmt = $pdo->prepare("SELECT user_agent, COUNT(*) as count FROM stats WHERE link_id = ? GROUP BY user_agent ORDER BY count DESC LIMIT 5");
            $stmt->execute([$linkId]);
            $rawBrowsers = $stmt->fetchAll();
            
            // تمیز کردن نام مرورگرها
            $browsers = [];
            foreach($rawBrowsers as $rb) {
                $info = parseUserAgent($rb['user_agent']);
                $name = $info['browser'];
                // ادغام مرورگرهای یکسان
                if(isset($browsers[$name])) $browsers[$name] += $rb['count'];
                else $browsers[$name] = $rb['count'];
            }

            $response = [
                'success' => true, 
                'daily' => $chartData, 
                'browsers' => $browsers
            ];
            break;

        // ---------------------------------------------------------------------
        // 4. افزودن دامنه جدید (Admin Only)
        // ---------------------------------------------------------------------
        case 'add_domain':
            if (!isAdmin()) throw new Exception('دسترسی غیرمجاز.');
            
            $domain = trim($_POST['domain'] ?? '');
            $domain = preg_replace('#^https?://#', '', $domain); // حذف http
            $domain = rtrim($domain, '/');

            if (empty($domain)) throw new Exception('نام دامنه نمی‌تواند خالی باشد.');

            try {
                $stmt = $pdo->prepare("INSERT INTO domains (domain) VALUES (?)");
                $stmt->execute([$domain]);
                $response = ['success' => true, 'message' => 'دامنه اضافه شد.'];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'این دامنه قبلا ثبت شده است.'];
            }
            break;
            
        // ---------------------------------------------------------------------
        // 5. تغییر وضعیت دامنه (Admin Only)
        // ---------------------------------------------------------------------
        case 'toggle_domain':
            if (!isAdmin()) throw new Exception('دسترسی غیرمجاز.');
            
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare("UPDATE domains SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            $response = ['success' => true, 'message' => 'وضعیت تغییر کرد.'];
            break;

        default:
            throw new Exception('عملیات نامعتبر است.');
    }

} catch (Exception $e) {
    // مدیریت خطاهای منطقی
    $response = ['success' => false, 'message' => $e->getMessage()];
} catch (PDOException $e) {
    // مدیریت خطاهای دیتابیس (بدون نمایش جزئیات فنی به کاربر)
    error_log($e->getMessage()); // ثبت در لاگ سرور
    $response = ['success' => false, 'message' => 'خطای پایگاه داده رخ داد.'];
}

echo json_encode($response);
exit;
?>