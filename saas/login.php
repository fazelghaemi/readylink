<?php
/**
 * RDYS - Enterprise Login Interface
 * Theme: Ready Studio (Teal & Black)
 * Version: 10.0.0 (Maximized)
 * * Logic: Dual Authentication (User/Email), Session Hardening, Smart Redirect.
 * * UX: Password Toggle, 3D Visuals, Error Feedback.
 */

ob_start();
require_once 'functions.php';

// اگر کاربر لاگین است، به داشبورد برود
if (isLoggedIn()) {
    header("Location: admin.php");
    exit;
}

$error = '';
$val_input = '';

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. امنیت: هانی‌پات
    if (!empty($_POST['website_honeypot'])) {
        die('Access Denied');
    }

    // 2. دریافت ورودی‌ها
    $input = cleanInput($_POST['username'] ?? ''); // می‌تواند یوزرنیم یا ایمیل باشد
    $password = $_POST['password'] ?? '';
    $val_input = $input;

    if (empty($input) || empty($password)) {
        $error = 'لطفا نام کاربری/ایمیل و رمز عبور را وارد کنید.';
    } else {
        try {
            // 3. جستجو در دیتابیس (دوگانه)
            $stmt = $pdo->prepare("
                SELECT id, username, password, role, is_verified 
                FROM users 
                WHERE username = :input OR email = :input 
                LIMIT 1
            ");
            $stmt->execute([':input' => $input]);
            $user = $stmt->fetch();

            // 4. بررسی رمز عبور
            if ($user && password_verify($password, $user['password'])) {
                
                // 5. ساخت سشن امن
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['ip'] = getClientIP();
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['last_activity'] = time();

                // 6. هدایت هوشمند (اگر ریدارکت در URL باشد)
                $redirect = $_GET['redirect'] ?? 'admin.php';
                // اعتبارسنجی آدرس بازگشتی (فقط دامنه‌های خودی)
                if (filter_var($redirect, FILTER_VALIDATE_URL) && strpos($redirect, $_SERVER['HTTP_HOST']) === false) {
                    $redirect = 'admin.php';
                }
                
                header("Location: " . $redirect);
                exit;

            } else {
                // تاخیر مصنوعی برای امنیت
                sleep(1); 
                $error = 'اطلاعات ورود اشتباه است.';
            }

        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            $error = 'خطای سیستمی رخ داد.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>ورود به پنل | RDYS</title>
    
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="favicon-black.png">
    <link rel="stylesheet" href="style.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        bg: "#010101", card: "#0D0D0D", primary: "#00B0A4", text: "#FFFFFF",
                        sec: "#9CA3AF", border: "#1F1F1F", error: "#FF3B30", success: "#00B0A4"
                    },
                    fontFamily: { sans: ['ReadyFont', 'Vazirmatn', 'sans-serif'] },
                    animation: {
                        'float-slow': 'float 8s ease-in-out infinite',
                    },
                    keyframes: {
                        float: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-20px)' } }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-bg text-text min-h-screen flex items-center justify-center p-4 relative overflow-hidden selection:bg-primary selection:text-white">

    <!-- Background Art -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute -top-[20%] -right-[10%] w-[700px] h-[700px] bg-primary/5 rounded-full blur-[150px] animate-float-slow"></div>
        <div class="absolute bottom-[10%] -left-[10%] w-[500px] h-[500px] bg-emerald-900/5 rounded-full blur-[100px]"></div>
        <div class="bg-noise absolute inset-0 opacity-[0.03]"></div>
    </div>

    <div class="w-full max-w-[420px] z-10 relative">
        
        <!-- Header -->
        <div class="text-center mb-8 animate-fade-in">
            <a href="index.php" class="inline-block mb-6 group">
                <div class="w-20 h-20 mx-auto bg-card border border-border rounded-2xl flex items-center justify-center shadow-[0_0_40px_rgba(0,176,164,0.1)] group-hover:border-primary/50 group-hover:shadow-[0_0_50px_rgba(0,176,164,0.2)] transition-all duration-500">
                    <img src="favicon-black.png" class="w-10 h-10 transform group-hover:scale-110 transition-transform duration-500" alt="Logo">
                </div>
            </a>
            <h1 class="text-3xl font-black text-white tracking-tight">خوش آمدید</h1>
            <p class="text-sec text-sm mt-2">وارد حساب کاربری خود شوید</p>
        </div>

        <!-- Login Card -->
        <div class="bg-card/90 backdrop-blur-xl border border-border rounded-3xl p-8 shadow-2xl relative overflow-hidden animate-fade-in" style="animation-delay: 0.1s;">
            
            <!-- Glow Effect -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-primary to-transparent opacity-60"></div>

            <!-- Error -->
            <?php if ($error): ?>
                <div class="bg-error/10 border border-error/20 text-error px-4 py-3.5 rounded-xl text-sm font-bold mb-6 flex items-start gap-3 animate-pulse">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off" id="loginForm">
                
                <!-- Honeypot -->
                <input type="text" name="website_honeypot" class="hidden" tabindex="-1">

                <!-- Username/Email -->
                <div class="mb-5 group">
                    <label class="block text-sec text-xs font-bold mb-2 uppercase tracking-wider group-focus-within:text-primary transition-colors">نام کاربری یا ایمیل</label>
                    <div class="relative">
                        <input type="text" name="username" value="<?php echo htmlspecialchars($val_input); ?>" required autofocus
                               class="w-full bg-bg border border-border text-white rounded-xl py-3.5 px-4 pl-12 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder-sec/30 text-sm ltr text-left" 
                               placeholder="user@example.com">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-sec group-focus-within:text-primary transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-8 group">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sec text-xs font-bold uppercase tracking-wider group-focus-within:text-primary transition-colors">رمز عبور</label>
                        <a href="recovery.php" class="text-[10px] text-sec hover:text-primary transition-colors">رمز را فراموش کردید؟</a>
                    </div>
                    <div class="relative">
                        <input type="password" name="password" id="passwordInput" required
                               class="w-full bg-bg border border-border text-white rounded-xl py-3.5 px-4 pl-12 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder-sec/30 text-sm ltr text-left" 
                               placeholder="••••••••">
                        
                        <!-- Toggle -->
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 left-0 pl-4 flex items-center cursor-pointer text-sec hover:text-white transition-colors focus:outline-none" title="نمایش رمز">
                            <svg id="eyeIconOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <svg id="eyeIconClosed" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.059 10.059 0 013.999-5.42m3.71-2.05m0 0L9 6m9.542-4.058a10.058 10.058 0 011.666 1.74M12 5c.478 0 .944.055 1.396.16m4.832 1.48L21 9m-9 0h.01M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" id="loginBtn" class="w-full bg-primary hover:bg-[#008F85] text-white font-black py-4 rounded-xl shadow-[0_0_20px_rgba(0,176,164,0.3)] hover:shadow-[0_0_30px_rgba(0,176,164,0.5)] transform transition-all active:scale-[0.98] flex items-center justify-center gap-2 group relative overflow-hidden">
                    <span class="relative z-10">ورود امن</span>
                    <svg class="w-5 h-5 relative z-10 transition-transform group-hover:translate-x-[-4px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700"></div>
                </button>

            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 animate-fade-in" style="animation-delay: 0.2s;">
            <p class="text-sec text-sm">
                حساب کاربری ندارید؟ 
                <a href="register.php" class="text-primary hover:text-white font-bold transition-colors inline-flex items-center gap-1 group">
                    ثبت‌نام کنید
                    <svg class="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
            </p>
            
            <div class="mt-8 flex items-center justify-center gap-4 text-xs text-sec/40">
                <a href="index.php" class="hover:text-sec">خانه</a>
                <span>&bull;</span>
                <a href="https://readystudio.ir/rdys-linkshortener/#rules" target="_blank" class="hover:text-sec">قوانین</a>
                <span>&bull;</span>
                <a href="https://readystudio.ir/contact-us" target="_blank" class="hover:text-sec">پشتیبانی</a>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script>
        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const iconOpen = document.getElementById('eyeIconOpen');
            const iconClosed = document.getElementById('eyeIconClosed');
            
            if (input.type === 'password') {
                input.type = 'text';
                iconOpen.classList.add('hidden');
                iconClosed.classList.remove('hidden');
            } else {
                input.type = 'password';
                iconOpen.classList.remove('hidden');
                iconClosed.classList.add('hidden');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const span = btn.querySelector('span');
            const svg = btn.querySelector('svg');
            
            btn.classList.add('opacity-80', 'cursor-not-allowed');
            span.textContent = 'در حال بررسی...';
            if(svg) svg.classList.add('hidden');
            
            // Add Spinner
            const loader = document.createElement('div');
            loader.className = 'w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin ml-2';
            btn.appendChild(loader);
        });
    </script>

</body>
</html>
<?php ob_end_flush(); ?>