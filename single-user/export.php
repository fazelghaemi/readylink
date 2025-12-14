<?php
/**
 * RDYS - Data Export Module (CSV/Excel)
 * * @author Senior Developer
 * @version 2.0.0
 * * خروجی گرفتن از اطلاعات لینک‌ها برای استفاده در اکسل.
 */

require_once 'functions.php';

// 1. بررسی دسترسی: فقط مدیر
if (!isAdmin()) {
    die("Access Denied");
}

// 2. تنظیم هدرها برای دانلود فایل
$filename = "rdys-links-" . date('Y-m-d-H-i') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// 3. باز کردن جریان خروجی (php://output)
// این کار باعث می‌شود داده‌ها مستقیم به مرورگر فرستاده شوند و در رم سرور انباشته نشوند
$output = fopen('php://output', 'w');

// 4. فیکس کردن مشکل نمایش فارسی در اکسل (BOM)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// 5. نوشتن سرستون‌ها (Headers)
fputcsv($output, [
    'شناسه (ID)', 
    'کد کوتاه (Short Code)', 
    'لینک اصلی (Long URL)', 
    'دامنه (Domain)',
    'تعداد بازدید (Views)', 
    'تاریخ ایجاد (Created At)'
]);

try {
    // 6. دریافت داده‌ها با کمترین فشار به سرور (Unbuffered Query)
    // استفاده از کرسر دیتابیس برای خواندن ردیف به ردیف
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    
    $stmt = $pdo->query("
        SELECT 
            l.id, 
            l.short_code, 
            l.long_url, 
            IFNULL(d.domain, 'Default') as domain_name,
            l.views, 
            l.created_at
        FROM links l
        LEFT JOIN domains d ON l.domain_id = d.id
        ORDER BY l.id DESC
    ");

    // 7. حلقه روی داده‌ها و نوشتن در CSV
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['short_code'],
            $row['long_url'],
            $row['domain_name'],
            $row['views'],
            $row['created_at']
        ]);
    }

} catch (PDOException $e) {
    // اگر خطایی در حین استریم رخ داد، چون هدرها ارسال شده‌اند، نمی‌توان کار زیادی کرد
    // اما در فایل CSV لاگ می‌شود
    fputcsv($output, ['Error exporting data', $e->getMessage()]);
}

// بستن فایل
fclose($output);
exit;
?>