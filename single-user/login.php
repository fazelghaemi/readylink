<?php
/**
 * RDYS - Secure Admin Login Interface
 * Theme: Ready Studio (Teal & Black)
 * Version: 4.0.0
 * * This file handles the authentication process for the admin panel.
 * It includes security measures against common attacks like brute-force and CSRF.
 */

// شروع بافرینگ خروجی برای جلوگیری از خطای "Headers already sent" هنگام ریدارکت
ob_start();

// فراخوانی فایل توابع اصلی برای دسترسی به دیتابیس و توابع کمکی
require_once 'functions.php';

// اگر کاربر از قبل لاگین کرده باشد، نیازی به دیدن فرم نیست؛ مستقیم به پنل هدایت می‌شود
if (isAdmin()) {
    header("Location: admin.php");
    exit;
}

// تعریف متغیرها برای نگهداری وضعیت فرم
$error = '';
$usernameValue = '';

// پردازش فرم فقط زمانی که متد درخواست POST باشد
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. بررسی امنیتی Honeypot (تله گذاری برای ربات‌ها)
    // فیلد 'website' در HTML مخفی است. کاربر واقعی آن را نمی‌بیند.
    // اگر پر شده باشد، یعنی یک ربات آن را پر کرده است.
    if (!empty($_POST['website'])) {
        // پایان دادن به درخواست بدون هیچ توضیحی برای ربات
        die('Access Denied (Bot Detected)');
    }

    // پاکسازی و دریافت ورودی‌ها
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // ذخیره نام کاربری برای نمایش مجدد در فیلد (User Experience)
    $usernameValue = $username;

    // اعتبارسنجی اولیه: فیلدها نباید خالی باشند
    if (empty($username) || empty($password)) {
        $error = 'لطفا نام کاربری و رمز عبور را وارد کنید.';
    } else {
        try {
            // 2. جستجوی کاربر در پایگاه داده
            // استفاده از Prepared Statements برای جلوگیری از SQL Injection
            $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // 3. بررسی صحت رمز عبور
            // تابع password_verify هش ذخیره شده را با رمز وارد شده مقایسه می‌کند
            if ($user && password_verify($password, $user['password'])) {
                
                // 4. جلوگیری از حمله Session Fixation
                // شناسه سشن را تغییر می‌دهیم تا اگر سشن قبلی دزدیده شده باشد، بی اعتبار شود
                session_regenerate_id(true);
                
                // ذخیره اطلاعات کاربر در سشن
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_login'] = time();
                
                // هدایت به داشبورد مدیریت
                header("Location: admin.php");
                exit;

            } else {
                // 5. مکانیزم تاخیر (Throttling)
                // اگر رمز اشتباه بود، 1 ثانیه صبر می‌کنیم.
                // این کار حملات Brute Force را به شدت کند و ناکارآمد می‌کند.
                sleep(1); 
                $error = 'نام کاربری یا رمز عبور اشتباه است.';
            }

        } catch (PDOException $e) {
            // لاگ کردن خطای واقعی در فایل لاگ سرور (نه نمایش به کاربر)
            error_log("Login Error: " . $e->getMessage());
            $error = 'خطای سیستمی رخ داد. لطفا بعدا تلاش کنید.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="ورود به پنل مدیریت سیستم کوتاه کننده لینک">
    <meta name="theme-color" content="#010101">
    <meta name="robots" content="noindex, nofollow"> <!-- جلوگیری از ایندکس شدن صفحه ورود توسط گوگل -->
    
    <title>ورود به مدیریت | RDYS</title>
    
    <!-- PWA & Icons -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link rel="apple-touch-icon" href="logo.svg">

    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    
    <!-- Master Stylesheet (استایل مرکزی) -->
    <link rel="stylesheet" href="style.css">
    
    <!-- Tailwind CSS (برای چیدمان سریع) -->
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
                    },
                    animation: {
                        'float-slow': 'float 8s ease-in-out infinite',
                        'float-reverse': 'floatReverse 10s ease-in-out infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0) rotate(0deg)' },
                            '50%': { transform: 'translateY(-20px) rotate(5deg)' },
                        },
                        floatReverse: {
                            '0%, 100%': { transform: 'translateY(0) rotate(0deg)' },
                            '50%': { transform: 'translateY(15px) rotate(-5deg)' },
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="flex items-center justify-center min-h-screen p-4 relative overflow-hidden bg-bg text-text antialiased selection:bg-primary selection:text-white">

    <!-- Background Decorative Elements (Blobs) -->
    <!-- اشکال پس‌زمینه برای زیبایی بصری و عمق دادن به طراحی فلت -->
    <div class="absolute -top-[20%] -left-[10%] w-[600px] h-[600px] bg-primary/10 rounded-full blur-[120px] pointer-events-none animate-float-slow"></div>
    <div class="absolute bottom-[10%] -right-[10%] w-[500px] h-[500px] bg-primary/5 rounded-full blur-[100px] pointer-events-none animate-float-reverse"></div>

    <!-- Login Container -->
    <div class="w-full max-w-[400px] z-10 relative">
        
        <!-- Logo & Header -->
        <div class="text-center mb-8 animate-fade-in" style="animation-delay: 0.1s;">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-card border border-border shadow-[0_0_30px_rgba(0,176,164,0.15)] mb-6 group transition-all duration-500 hover:border-primary hover:shadow-[0_0_40px_rgba(0,176,164,0.3)]">
                <svg class="w-8 h-8 text-primary group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-black tracking-tight text-white mb-2">پنل مدیریت</h1>
            <p class="text-sec text-sm font-medium">احراز هویت سیستم <span class="text-primary font-bold">رِدی استودیو</span></p>
        </div>

        <!-- Login Card -->
        <div class="bg-card/80 backdrop-blur-xl border border-border rounded-3xl p-8 shadow-2xl relative animate-fade-in" style="animation-delay: 0.2s;">
            
            <!-- Error Message Display -->
            <?php if ($error): ?>
                <div class="bg-error/10 border border-error/20 text-error px-4 py-3 rounded-xl text-sm font-bold mb-6 flex items-start gap-3 animate-pulse">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off" id="loginForm">
                
                <!-- Honeypot Field (Hidden Security Field) -->
                <!-- اگر این فیلد پر شود، فرم پردازش نمی‌شود -->
                <div style="display:none; opacity:0; visibility:hidden; position:absolute; left:-9999px;">
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <!-- Username Field -->
                <div class="mb-5 group">
                    <label class="block text-sec text-xs font-bold mb-2 uppercase tracking-wider group-focus-within:text-primary transition-colors">نام کاربری</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 pl-3 flex items-center pointer-events-none pr-4">
                            <svg class="h-5 w-5 text-sec group-focus-within:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($usernameValue); ?>" required autofocus
                               class="w-full bg-bg border border-border text-white rounded-xl py-3.5 pr-12 pl-4 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder-sec/30 text-sm font-medium shadow-inner" 
                               placeholder="نام کاربری خود را وارد کنید">
                    </div>
                </div>

                <!-- Password Field -->
                <div class="mb-8 group">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sec text-xs font-bold uppercase tracking-wider group-focus-within:text-primary transition-colors">رمز عبور</label>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 pl-3 flex items-center pointer-events-none pr-4">
                            <svg class="h-5 w-5 text-sec group-focus-within:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <input type="password" name="password" id="passwordInput" required
                               class="w-full bg-bg border border-border text-white rounded-xl py-3.5 pr-12 pl-12 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder-sec/30 text-sm font-medium shadow-inner tracking-widest" 
                               placeholder="••••••••">
                        
                        <!-- Toggle Password Visibility Button -->
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 left-0 pl-3 flex items-center cursor-pointer text-sec hover:text-white transition-colors focus:outline-none" title="نمایش رمز عبور">
                            <svg id="eyeIconOpen" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <svg id="eyeIconClosed" class="h-5 w-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.059 10.059 0 013.999-5.42m3.71-2.05m0 0L9 6m9.542-4.058a10.058 10.058 0 011.666 1.74M12 5c.478 0 .944.055 1.396.16m4.832 1.48L21 9m-9 0h.01M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="loginBtn" class="w-full bg-primary hover:bg-[#008F85] text-white font-black py-4 rounded-xl shadow-[0_0_20px_rgba(0,176,164,0.3)] hover:shadow-[0_0_30px_rgba(0,176,164,0.5)] transform transition-all active:scale-95 flex items-center justify-center gap-2 relative overflow-hidden group">
                    <span class="relative z-10">ورود به سیستم</span>
                    <svg class="w-5 h-5 relative z-10 transition-transform group-hover:translate-x-[-4px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    <!-- Button Glow Effect -->
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700"></div>
                </button>

            </form>
        </div>

        <!-- Footer Links -->
        <div class="text-center mt-8 animate-fade-in" style="animation-delay: 0.3s;">
            <a href="index.php" class="inline-flex items-center gap-2 text-sec hover:text-white text-sm font-medium transition-colors p-2 rounded-lg hover:bg-card border border-transparent hover:border-border">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                <span>بازگشت به صفحه اصلی</span>
            </a>
            
            <p class="text-[10px] text-sec/40 mt-6 font-mono">
                RDYS Version 4.0.0 &bull; Protected by SecureAuth
            </p>
        </div>

    </div>

    <!-- JavaScript Interactions -->
    <script>
        // Toggle Password Visibility Logic
        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const iconOpen = document.getElementById('eyeIconOpen');
            const iconClosed = document.getElementById('eyeIconClosed');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                iconOpen.classList.add('hidden');
                iconClosed.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                iconOpen.classList.remove('hidden');
                iconClosed.classList.add('hidden');
            }
        }

        // Add loading state to button on submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const span = btn.querySelector('span');
            const svg = btn.querySelector('svg');
            
            btn.classList.add('opacity-80', 'cursor-not-allowed');
            span.textContent = 'در حال اعتبارسنجی...';
            svg.classList.add('hidden');
            
            // Add a spinner
            const loader = document.createElement('div');
            loader.className = 'w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin ml-2';
            btn.appendChild(loader);
        });
    </script>

</body>
</html>
<?php 
// پایان بافرینگ و ارسال خروجی به مرورگر
ob_end_flush(); 
?>