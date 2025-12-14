<?php
/**
 * RDYS - Advanced Database Manager
 * Theme: Ready Studio (Teal & Black)
 * Version: 12.0.0 (Multi-Action)
 * * Actions:
 * 1. Install: Create tables if not exists.
 * 2. Update: Add missing columns to existing tables.
 * 3. Rebuild: Drop all tables and reinstall (Reset).
 */

require_once 'config.php';

// تشخیص محیط
$currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$cleanDomain = preg_replace('/^www\./', '', $currentHost);
$defaultAdminEmail = 'admin@' . $cleanDomain;

$message = '';
$status = ''; // success | error | warning
$systemCheck = true;

// بررسی‌های سیستمی
$phpVersion = phpversion();
$pdoDriver = extension_loaded('pdo_mysql');

if (version_compare($phpVersion, '7.4.0', '<') || !$pdoDriver) {
    $status = 'error';
    $message = "سیستم شما حداقل نیازها (PHP 7.4+ و PDO) را ندارد.";
    $systemCheck = false;
}

// توابع کمکی SQL
function createTables($pdo) {
    // 1. Plans
    $pdo->exec("CREATE TABLE IF NOT EXISTS `plans` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL,
        `price` decimal(10,2) DEFAULT 0.00,
        `link_limit` int(11) DEFAULT 50,
        `domain_limit` int(11) DEFAULT 0,
        `has_api` tinyint(1) DEFAULT 0,
        `is_default` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `email` varchar(100) NOT NULL,
        `username` varchar(50) DEFAULT NULL,
        `password` varchar(255) NOT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `websites` text DEFAULT NULL,
        `role` enum('admin','user') DEFAULT 'user',
        `plan_id` int(11) DEFAULT 1,
        `api_token` varchar(64) DEFAULT NULL,
        `is_verified` tinyint(1) DEFAULT 0,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`),
        UNIQUE KEY `api_token` (`api_token`),
        KEY `plan_id` (`plan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Domains
    $pdo->exec("CREATE TABLE IF NOT EXISTS `domains` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `domain` varchar(255) NOT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `is_verified` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `domain` (`domain`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Links
    $pdo->exec("CREATE TABLE IF NOT EXISTS `links` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `domain_id` int(11) DEFAULT NULL,
        `long_url` text NOT NULL,
        `short_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        `views` int(11) DEFAULT 0,
        `is_public` tinyint(1) DEFAULT 1,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `short_code` (`short_code`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. Stats
    $pdo->exec("CREATE TABLE IF NOT EXISTS `stats` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `link_id` int(11) NOT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `referrer` varchar(500) DEFAULT NULL,
        `user_agent` varchar(255) DEFAULT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `link_id` (`link_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

function seedData($pdo, $adminEmail, $domain) {
    // Plans
    if ($pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO plans (name, price, link_limit, domain_limit, has_api, is_default) VALUES 
            ('رایگان', 0, 50, 0, 0, 1),
            ('حرفه‌ای', 99000, 5000, 3, 1, 0),
            ('نامحدود', 299000, 1000000, 10, 1, 0)");
    }
    
    // Admin User
    if ($pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() == 0) {
        $pass = password_hash('admin', PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO users (email, username, password, role, plan_id, api_token, is_verified) VALUES (?, 'admin', ?, 'admin', 3, ?, 1)");
        $stmt->execute([$adminEmail, $pass, $token]);
    }
    
    // Default Domain
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM domains WHERE domain = ?");
    $stmt->execute([$domain]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO domains (user_id, domain, is_active, is_verified) VALUES (1, ?, 1, 1)");
        $stmt->execute([$domain]);
    }
}

// پردازش درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $systemCheck) {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        switch ($action) {
            case 'install':
                createTables($pdo);
                seedData($pdo, $defaultAdminEmail, $cleanDomain);
                $status = 'success';
                $message = "نصب اولیه با موفقیت انجام شد. جداول ساخته شدند.";
                break;

            case 'update':
                // فقط ستون‌های جدید را اضافه کن
                $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
                $updates = [];
                if (!in_array('phone', $columns)) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN `phone` varchar(20) DEFAULT NULL AFTER `password`");
                    $updates[] = "ستون شماره تلفن";
                }
                if (!in_array('websites', $columns)) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN `websites` text DEFAULT NULL AFTER `phone`");
                    $updates[] = "ستون وب‌سایت‌ها";
                }
                // چک کردن سایر جداول در صورت نیاز...
                
                if (empty($updates)) {
                    $status = 'warning';
                    $message = "دیتابیس شما به‌روز است و نیازی به تغییر نداشت.";
                } else {
                    $status = 'success';
                    $message = "بروزرسانی انجام شد: " . implode('، ', $updates) . " اضافه شدند.";
                }
                break;

            case 'rebuild':
                // حذف تمام جداول و نصب مجدد
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $tables = ['stats', 'links', 'domains', 'users', 'plans'];
                foreach ($tables as $table) {
                    $pdo->exec("DROP TABLE IF EXISTS `$table`");
                }
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                createTables($pdo);
                seedData($pdo, $defaultAdminEmail, $cleanDomain);
                
                $status = 'success';
                $message = "دیتابیس کاملاً پاکسازی و بازسازی شد (Reset). همه چیز به تنظیمات اولیه بازگشت.";
                break;
        }

    } catch (PDOException $e) {
        $status = 'error';
        $message = 'خطا: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت دیتابیس | RDYS</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { bg: "#010101", card: "#0D0D0D", primary: "#00B0A4", text: "#FFFFFF", sec: "#9CA3AF", border: "#1F1F1F", success: "#00B0A4", error: "#FF3B30", warning: "#FFCC00" }, fontFamily: { sans: ['ReadyFont', 'Vazirmatn', 'sans-serif'] } } } }
    </script>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-bg text-text selection:bg-primary selection:text-white">
    <div class="w-full max-w-lg z-10 relative animate-fade-in bg-card border border-border rounded-3xl p-8 shadow-2xl text-center">
        
        <div class="mb-6 flex justify-center">
            <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center border border-primary/20">
                <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
            </div>
        </div>
        
        <h1 class="text-2xl font-black mb-2 text-white">مدیریت پایگاه داده</h1>
        <p class="text-sec text-sm mb-8">عملیات مورد نظر خود را انتخاب کنید</p>
        
        <?php if ($message): ?>
            <div class="p-4 rounded-xl mb-6 text-sm border flex items-start gap-3 text-right
                <?php echo $status === 'success' ? 'bg-success/10 text-success border-success/20' : ($status === 'warning' ? 'bg-warning/10 text-warning border-warning/20' : 'bg-error/10 text-error border-error/20'); ?>">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <?php if($status==='success'): ?><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    <?php elseif($status==='warning'): ?><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    <?php else: ?><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path><?php endif; ?>
                </svg>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <?php if($systemCheck): ?>
            <div class="space-y-3">
                
                <!-- Install Button -->
                <form method="post">
                    <input type="hidden" name="action" value="install">
                    <button type="submit" class="w-full bg-primary hover:bg-[#008F85] text-white font-bold py-3.5 rounded-xl shadow-glow transition-all flex items-center justify-center gap-2 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        <span>نصب اولیه (فقط جداول جدید)</span>
                    </button>
                </form>

                <!-- Update Button -->
                <form method="post">
                    <input type="hidden" name="action" value="update">
                    <button type="submit" class="w-full bg-card border border-primary/30 text-primary hover:bg-primary hover:text-white font-bold py-3.5 rounded-xl transition-all flex items-center justify-center gap-2 group">
                        <svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span>بروزرسانی ساختار (تعمیر)</span>
                    </button>
                </form>

                <!-- Rebuild Button -->
                <form method="post" onsubmit="return confirm('هشدار جدی: آیا مطمئن هستید؟\nتمام اطلاعات کاربران، لینک‌ها و آمار به طور کامل حذف خواهد شد.\nاین عملیات غیرقابل بازگشت است.');">
                    <input type="hidden" name="action" value="rebuild">
                    <button type="submit" class="w-full bg-error/10 border border-error/30 text-error hover:bg-error hover:text-white font-bold py-3.5 rounded-xl transition-all flex items-center justify-center gap-2 group mt-6">
                        <svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        <span>بازسازی کامل (حذف و نصب مجدد)</span>
                    </button>
                </form>

            </div>
            
            <p class="mt-6 text-xs text-sec/50">پیشنهاد می‌شود پس از اتمام کار، این فایل را حذف کنید.</p>
        <?php endif; ?>

        <div class="mt-6 pt-6 border-t border-border">
            <a href="index.php" class="text-sm font-bold text-sec hover:text-white transition-colors">بازگشت به صفحه اصلی</a>
        </div>
    </div>
</body>
</html>