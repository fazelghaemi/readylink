<?php
/**
 * RDYS - Database Installer & System Check
 * Theme: Ready Studio (Teal & Black)
 * Version: 4.0.0
 * * This script initializes the database tables and sets up the default admin user.
 * * It includes system requirement checks and secure password hashing.
 */

// فراخوانی تنظیمات اصلی برای اتصال به دیتابیس
require_once 'config.php';

// متغیرهای وضعیت
$message = '';
$status = ''; // 'success', 'error', 'warning'
$systemCheck = true;
$phpVersion = phpversion();
$pdoDriver = extension_loaded('pdo_mysql');

// 1. بررسی حداقل نیازهای سیستم
if (version_compare($phpVersion, '7.4.0', '<')) {
    $status = 'error';
    $message = "نسخه PHP شما ($phpVersion) قدیمی است. حداقل نسخه 7.4 نیاز است.";
    $systemCheck = false;
} elseif (!$pdoDriver) {
    $status = 'error';
    $message = "درایور PDO MySQL روی سرور شما فعال نیست.";
    $systemCheck = false;
}

// 2. پردازش نصب (زمانی که دکمه کلیک شود)
if (isset($_POST['install']) && $systemCheck) {
    try {
        // نکته فنی: در MySQL دستورات DDL (مانند CREATE TABLE) باعث Commit ضمنی می‌شوند.
        // بنابراین از beginTransaction استفاده نمی‌کنیم تا از بروز خطای "There is no active transaction" جلوگیری کنیم.

        // الف) جدول کاربران (Users)
        // ایندکس روی username برای سرعت در لاگین
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` enum('admin','user') DEFAULT 'user',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // ب) جدول دامنه‌ها (Domains)
        // برای پشتیبانی از قابلیت چند دامنه‌ای
        $pdo->exec("CREATE TABLE IF NOT EXISTS `domains` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `domain` varchar(255) NOT NULL,
            `is_active` tinyint(1) DEFAULT 1,
            PRIMARY KEY (`id`),
            UNIQUE KEY `domain` (`domain`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // پ) جدول لینک‌ها (Links)
        // استفاده از collation باینری برای short_code جهت حساسیت به حروف بزرگ و کوچک (Case Sensitive)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `links` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `domain_id` int(11) DEFAULT 1,
            `long_url` text NOT NULL,
            `short_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            `views` int(11) DEFAULT 0,
            `is_public` tinyint(1) DEFAULT 1,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `short_code` (`short_code`),
            KEY `user_id` (`user_id`),
            KEY `domain_id` (`domain_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // ت) جدول آمار (Stats)
        // استفاده از Cascade Delete برای پاکسازی خودکار آمار در صورت حذف لینک
        $pdo->exec("CREATE TABLE IF NOT EXISTS `stats` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `link_id` int(11) NOT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `referrer` varchar(500) DEFAULT NULL,
            `user_agent` varchar(255) DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `link_id` (`link_id`),
            CONSTRAINT `fk_stats_link` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. درج داده‌های اولیه (Seed Data)

        // ایجاد کاربر ادمین پیش‌فرض (اگر وجود نداشته باشد)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            // هش کردن رمز عبور با الگوریتم قدرتمند پیش‌فرض (Bcrypt)
            $adminPass = password_hash('admin', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')");
            $stmt->execute([$adminPass]);
        }

        // ایجاد دامنه پیش‌فرض بر اساس تنظیمات SITE_URL
        $defaultDomain = parse_url(SITE_URL, PHP_URL_HOST);
        if ($defaultDomain) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM domains WHERE domain = ?");
            $stmt->execute([$defaultDomain]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO domains (domain) VALUES (?)");
                $stmt->execute([$defaultDomain]);
            }
        }

        $status = 'success';
        $message = 'نصب با موفقیت انجام شد! جداول پایگاه داده ایجاد شدند.';

    } catch (PDOException $e) {
        $status = 'error';
        $message = 'خطا در نصب دیتابیس: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب‌کننده سیستم | RDYS</title>
    
    <!-- PWA & Icons -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link rel="apple-touch-icon" href="logo.svg">

    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    
    <!-- Master Stylesheet -->
    <link rel="stylesheet" href="style.css">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        bg: "#010101", 
                        card: "#0D0D0D", 
                        primary: "#00B0A4", 
                        text: "#FFFFFF",
                        sec: "#9CA3AF", 
                        border: "#1F1F1F", 
                        success: "#00B0A4", 
                        error: "#FF3B30",
                    },
                    fontFamily: {
                        sans: ['ReadyFont', 'Vazirmatn', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-bg text-text antialiased selection:bg-primary selection:text-white relative overflow-hidden">

    <!-- Background Effects -->
    <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-primary via-emerald-500 to-primary"></div>
    <div class="absolute -top-[20%] -right-[10%] w-[600px] h-[600px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[10%] -left-[10%] w-[500px] h-[500px] bg-primary/5 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="w-full max-w-lg z-10 relative animate-fade-in">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-card border border-border rounded-full shadow-[0_0_30px_rgba(0,176,164,0.2)] mb-6">
                <svg class="w-10 h-10 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
            <h1 class="text-3xl font-black tracking-tight mb-2">نصب RDYS</h1>
            <p class="text-sec">نسخه 4.0.0 &bull; Ready Studio Edition</p>
        </div>

        <!-- Main Card -->
        <div class="bg-card/90 backdrop-blur-xl border border-border rounded-3xl p-8 shadow-2xl">
            
            <?php if ($status === 'success'): ?>
                <!-- Success State -->
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-success/10 rounded-full flex items-center justify-center mx-auto mb-4 border border-success/20">
                        <svg class="w-8 h-8 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h2 class="text-xl font-bold text-white mb-2">تبریک! نصب انجام شد</h2>
                    <p class="text-sec text-sm">پایگاه داده با موفقیت پیکربندی شد.</p>
                </div>

                <!-- Credentials Box -->
                <div class="bg-bg border border-border rounded-2xl p-6 mb-8 relative group overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-primary"></div>
                    <p class="text-xs text-primary font-bold uppercase tracking-wider mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 14l-1 1-1 1H6v-1l4-4 4-4v2.58a2 2 0 01.782-1.782l.17-.17a6 6 0 013.37-1.48z"></path></svg>
                        اطلاعات ورود مدیر
                    </p>
                    
                    <div class="flex justify-between items-center mb-3 pb-3 border-b border-border/50">
                        <span class="text-sec text-sm">نام کاربری:</span>
                        <code class="text-white font-mono bg-card px-2 py-1 rounded border border-border">admin</code>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sec text-sm">رمز عبور:</span>
                        <code class="text-white font-mono bg-card px-2 py-1 rounded border border-border">admin</code>
                    </div>
                </div>

                <!-- Actions -->
                <a href="index.php" class="block w-full bg-primary hover:bg-[#008F85] text-white font-black text-center py-4 rounded-xl transition-all shadow-[0_0_20px_rgba(0,176,164,0.3)] hover:shadow-[0_0_30px_rgba(0,176,164,0.5)] transform hover:scale-[1.02]">
                    ورود به صفحه اصلی
                </a>
                
                <p class="mt-6 text-center text-xs text-error bg-error/5 p-3 rounded-lg border border-error/10">
                    <span class="font-bold">هشدار امنیتی:</span> لطفا پس از اطمینان از صحت نصب، فایل <code>install.php</code> را از هاست خود حذف کنید.
                </p>

            <?php elseif ($status === 'error'): ?>
                <!-- Error State -->
                <div class="bg-error/10 border border-error/20 text-error p-5 rounded-xl mb-6 text-sm flex items-start gap-3">
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <div>
                        <p class="font-bold mb-1">خطا در فرآیند نصب:</p>
                        <p class="opacity-80"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
                
                <form method="post">
                    <button type="submit" name="install" class="w-full bg-primary hover:bg-[#008F85] text-white font-bold py-4 rounded-xl transition-all shadow-lg">
                        تلاش مجدد
                    </button>
                </form>

            <?php else: ?>
                <!-- Initial State -->
                
                <!-- System Check List -->
                <div class="space-y-3 mb-8">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-bg border <?php echo version_compare($phpVersion, '7.4.0', '>=') ? 'border-success/30' : 'border-error/30'; ?>">
                        <div class="flex items-center gap-3">
                            <span class="text-sec text-sm">نسخه PHP</span>
                            <span class="text-xs font-mono bg-card px-2 py-0.5 rounded border border-border"><?php echo $phpVersion; ?></span>
                        </div>
                        <?php if(version_compare($phpVersion, '7.4.0', '>=')): ?>
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <?php else: ?>
                            <svg class="w-5 h-5 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-bg border <?php echo $pdoDriver ? 'border-success/30' : 'border-error/30'; ?>">
                        <div class="flex items-center gap-3">
                            <span class="text-sec text-sm">اکستنشن PDO MySQL</span>
                        </div>
                        <?php if($pdoDriver): ?>
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <?php else: ?>
                            <svg class="w-5 h-5 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 rounded-xl bg-bg border border-success/30">
                        <div class="flex items-center gap-3">
                            <span class="text-sec text-sm">فونت اختصاصی</span>
                            <span class="text-xs font-mono bg-card px-2 py-0.5 rounded border border-border">ReadyFont</span>
                        </div>
                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                </div>

                <p class="text-sm text-sec mb-6 leading-relaxed text-center">
                    این اسکریپت جداول مورد نیاز (کاربران، لینک‌ها، آمار، دامنه‌ها) را در دیتابیس ایجاد می‌کند. <br>
                    <span class="text-primary font-bold">آیا آماده هستید؟</span>
                </p>

                <form method="post">
                    <button type="submit" name="install" <?php echo !$systemCheck ? 'disabled' : ''; ?> 
                            class="group w-full bg-primary hover:bg-[#008F85] disabled:bg-gray-600 disabled:cursor-not-allowed text-white font-black py-4 rounded-xl transition-all shadow-[0_0_20px_rgba(0,176,164,0.3)] hover:shadow-[0_0_30px_rgba(0,176,164,0.5)] transform active:scale-95 flex items-center justify-center gap-2">
                        <span>شروع نصب اتوماتیک</span>
                        <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </button>
                </form>
            <?php endif; ?>

        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-xs text-sec/30 font-mono">Powered by RDYS Core v4.0</p>
        </div>

    </div>
</body>
</html>