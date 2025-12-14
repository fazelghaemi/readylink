<?php
/**
 * RDYS - Enterprise Data Export Engine
 * Theme: Ready Studio (Teal & Black)
 * Version: 10.0.0 (Maximized)
 * * Logic: Streaming CSV generation, Memory optimization, Role-based filtering.
 * * Output: UTF-8 CSV file compatible with Excel/Sheets.
 */

// لود کردن هسته (بدون خروجی HTML)
require_once 'functions.php';

// 1. امنیت: بررسی لاگین
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$isAdmin = ($currentUser['role'] === 'admin');

// 2. تنظیم نام فایل
// اگر ادمین است: rdys-full-export-DATE.csv
// اگر کاربر است: rdys-my-links-DATE.csv
$prefix = $isAdmin ? "rdys-full-data-" : "rdys-my-links-";
$filename = $prefix . date('Y-m-d-H-i') . ".csv";

// 3. ارسال هدرهای دانلود (HTTP Headers)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// 4. باز کردن استریم خروجی (php://output)
// این روش داده‌ها را مستقیم به مرورگر می‌فرستد و در رم سرور نگه نمی‌دارد
$output = fopen('php://output', 'w');

// 5. اضافه کردن BOM برای پشتیبانی از کاراکترهای فارسی در اکسل
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// 6. تعریف سرستون‌ها (Headers)
$headers = [
    'شناسه (ID)', 
    'لینک کوتاه (Short URL)', 
    'لینک اصلی (Original URL)', 
    'دامنه (Domain)',
    'تعداد بازدید (Views)', 
    'تاریخ ایجاد (Created At)'
];

// ستون‌های اضافی برای ادمین
if ($isAdmin) {
    array_push($headers, 'سازنده (User)', 'ایمیل (Email)', 'وضعیت دامنه');
}

fputcsv($output, $headers);

try {
    // 7. اجرای کوئری بهینه (Unbuffered)
    // غیرفعال کردن بافر MySQL برای صرفه‌جویی در رم
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    
    // ساخت کوئری داینامیک
    $sql = "
        SELECT 
            l.id, 
            l.short_code, 
            l.long_url, 
            l.views, 
            l.created_at,
            d.domain,
            d.is_active as domain_active,
            u.username,
            u.email
        FROM links l
        LEFT JOIN domains d ON l.domain_id = d.id
        LEFT JOIN users u ON l.user_id = u.id
    ";

    // اعمال فیلتر امنیتی (اگر ادمین نیست، فقط لینک‌های خودش)
    if (!$isAdmin) {
        $sql .= " WHERE l.user_id = :user_id";
    }
    
    $sql .= " ORDER BY l.id DESC";

    $stmt = $pdo->prepare($sql);
    
    if (!$isAdmin) {
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    }
    
    $stmt->execute();

    // 8. دریافت پروتکل سایت
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $baseHost = $_SERVER['HTTP_HOST'];

    // 9. پردازش و نوشتن ردیف‌ها (Loop & Write)
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        
        // تعیین دامنه نهایی (اگر دامنه اختصاصی نبود، از دامنه پیش‌فرض استفاده کن)
        $domainName = !empty($row['domain']) ? $row['domain'] : $baseHost;
        $fullShortUrl = $protocol . $domainName . '/' . $row['short_code'];
        
        // آماده‌سازی آرایه داده
        $csvRow = [
            $row['id'],
            $fullShortUrl,
            $row['long_url'],
            $domainName,
            number_format($row['views']),
            $row['created_at']
        ];

        // داده‌های اضافی برای ادمین
        if ($isAdmin) {
            $domainStatus = ($row['domain_active'] === null) ? 'N/A' : ($row['domain_active'] ? 'Active' : 'Inactive');
            array_push($csvRow, $row['username'], $row['email'], $domainStatus);
        }

        // نوشتن در خروجی
        fputcsv($output, $csvRow);
    }

} catch (PDOException $e) {
    // در صورت خطا، پیام را در فایل CSV می‌نویسیم تا کاربر متوجه شود
    fputcsv($output, ['ERROR: Could not export data', $e->getMessage()]);
}

// بستن فایل و پایان اسکریپت
fclose($output);
exit;
?>