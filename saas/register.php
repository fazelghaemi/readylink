<?php
/**
 * RDYS - Enterprise User Registration
 * Theme: Ready Studio (Teal & Black)
 * Version: 10.0.0 (Maximized)
 * * Logic: Handles user creation, plan assignment, and dynamic profile data (Phone/Websites).
 * * Security: Honeypot, Input Sanitization, Password Hashing.
 */

ob_start();
require_once 'functions.php';

// اگر کاربر لاگین است، نیازی به ثبت‌نام مجدد نیست
if (isLoggedIn()) {
    header("Location: admin.php");
    exit;
}

$error = '';
$success = '';

// متغیرها برای پر کردن مجدد فرم در صورت خطا
$val_username = '';
$val_email = '';
$val_phone = '';
$val_websites = [];

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. امنیت: بررسی Honeypot (فیلد مخفی برای به دام انداختن ربات‌ها)
    if (!empty($_POST['website_honeypot'])) {
        die('Access Denied (Bot Detected)');
    }

    // 2. دریافت و پاکسازی ورودی‌ها
    $username = cleanInput($_POST['username'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone = cleanInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $websites_raw = $_POST['websites'] ?? [];
    $terms = isset($_POST['terms']);

    // ذخیره مقادیر برای نمایش مجدد
    $val_username = $username;
    $val_email = $email;
    $val_phone = $phone;
    $val_websites = $websites_raw;

    // فیلتر کردن لیست سایت‌ها (حذف موارد خالی)
    $websites = array_filter($websites_raw, function($site) {
        return !empty(trim($site));
    });

    // 3. اعتبارسنجی داده‌ها
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'لطفا تمام فیلدهای ستاره‌دار (*) را تکمیل کنید.';
    } elseif (!$terms) {
        $error = 'پذیرش قوانین و مقررات الزامی است.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'فرمت ایمیل وارد شده صحیح نیست.';
    } elseif (strlen($password) < 6) {
        $error = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
    } elseif ($password !== $confirm_password) {
        $error = 'رمز عبور و تکرار آن مطابقت ندارند.';
    } else {
        try {
            // 4. بررسی یکتایی ایمیل و نام کاربری
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->execute([$email, $username]);
            
            if ($stmt->fetch()) {
                $error = 'این ایمیل یا نام کاربری قبلاً در سیستم ثبت شده است.';
            } else {
                // 5. دریافت پلن پیش‌فرض
                $planStmt = $pdo->prepare("SELECT id FROM plans WHERE is_default = 1 LIMIT 1");
                $planStmt->execute();
                $defaultPlanId = $planStmt->fetchColumn() ?: 1;

                // 6. آماده‌سازی داده‌ها برای درج
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $apiToken = generateApiToken();
                $websitesJson = !empty($websites) ? json_encode(array_values($websites)) : null;

                // 7. درج کاربر جدید
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, phone, websites, role, plan_id, api_token, created_at, is_verified) 
                    VALUES (?, ?, ?, ?, ?, 'user', ?, ?, NOW(), 0)
                ");
                
                if ($insertStmt->execute([$username, $email, $hashedPassword, $phone, $websitesJson, $defaultPlanId, $apiToken])) {
                    // 8. لاگین خودکار (Auto-Login)
                    $userId = $pdo->lastInsertId();
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = 'user';
                    
                    // هدایت به داشبورد با پیام خوش‌آمد
                    header("Location: admin.php?welcome=1");
                    exit;
                } else {
                    $error = 'خطا در ثبت نام. لطفا مجدد تلاش کنید.';
                }
            }
        } catch (PDOException $e) {
            error_log("Register Error: " . $e->getMessage());
            $error = 'خطای سیستمی رخ داد. لطفا با پشتیبانی تماس بگیرید.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>عضویت در ردی استودیو | RDYS</title>
    
    <!-- PWA & Icons -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="favicon-black.png">
    <link rel="stylesheet" href="style.css">
    
    <!-- Tailwind -->
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
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        float: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-15px)' } }
                    }
                }
            }
        }
    </script>
    <style>
        .website-field { animation: slideIn 0.3s ease-out forwards; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-bg text-text min-h-screen flex items-center justify-center p-4 relative overflow-x-hidden selection:bg-primary selection:text-white">

    <!-- Background Elements -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute -top-[10%] -left-[10%] w-[600px] h-[600px] bg-primary/5 rounded-full blur-[120px] animate-pulse"></div>
        <div class="absolute bottom-[10%] -right-[10%] w-[500px] h-[500px] bg-purple-900/5 rounded-full blur-[100px]"></div>
        <div class="bg-noise absolute inset-0 opacity-[0.03]"></div>
    </div>

    <div class="w-full max-w-2xl z-10 relative">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="index.php" class="inline-block mb-4 hover:scale-110 transition-transform duration-300">
                <img src="favicon-black.png" class="w-16 h-16 rounded-2xl shadow-glow" alt="Logo">
            </a>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tight">
                به خانواده <span class="text-primary">رِدی استودیو</span> بپیوندید
            </h1>
            <p class="text-sec text-sm mt-2">شروع مدیریت حرفه‌ای لینک‌ها با دامنه اختصاصی</p>
        </div>

        <!-- Registration Card -->
        <div class="bg-card/90 backdrop-blur-xl border border-border rounded-3xl p-6 md:p-10 shadow-2xl relative overflow-hidden">
            
            <!-- Decorative Top Border -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-primary to-transparent opacity-60"></div>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="bg-error/10 border border-error/20 text-error px-4 py-4 rounded-xl text-sm font-bold mb-8 flex items-start gap-3 animate-pulse">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off" id="regForm">
                
                <!-- Honeypot -->
                <input type="text" name="website_honeypot" class="hidden" tabindex="-1">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    
                    <!-- Username -->
                    <div class="group">
                        <label class="block text-sec text-xs font-bold mb-2 uppercase tracking-wider group-focus-within:text-primary transition-colors">نام کاربری <span class="text-error">*</span></label>
                        <div class="relative">
                            <input type="text" name="username" value="<?php echo htmlspecialchars($val_username); ?>" required 
                                   class="w-full bg-bg border border-border text-white rounded-xl py-3 px-4 pl-10 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all ltr text-left placeholder-sec/30 text-sm" 
                                   placeholder="username">
                            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-sec"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></div>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="group">
                        <label class="block text-sec text-xs font-bold mb-2 uppercase tracking-wider group-focus-within:text-primary transition-colors">ایمیل <span class="text-error">*</span></label>
                        <div class="relative">
                            <input type="email" name="email" value="<?php echo htmlspecialchars($val_email); ?>" required 
                                   class="w-full bg-bg border border-border text-white rounded-xl py-3 px-4 pl-10 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all ltr text-left placeholder-sec/30 text-sm" 
                                   placeholder="name@example.com">
                            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-sec"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg></div>
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="group">
                        <label class="block text-sec text-xs font-bold mb-2 uppercase tracking-wider group-focus-within:text-primary transition-colors">شماره موبایل</label>
                        <div class="relative">
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($val_phone); ?>" 
                                   class="w-full bg-bg border border-border text-white rounded-xl py-3 px-4 pl-10 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all ltr text-left placeholder-sec/30 text-sm" 
                                   placeholder="0912...">
                            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-sec"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg></div>
                        </div>
                    </div>

                    <!-- Password Section -->
                    <div class="group">
                        <label class="block text-sec text-xs font-bold mb-2 uppercase tracking-wider group-focus-within:text-primary transition-colors">رمز عبور <span class="text-error">*</span></label>
                        <div class="relative">
                            <input type="password" name="password" required 
                                   class="w-full bg-bg border border-border text-white rounded-xl py-3 px-4 pl-10 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all ltr text-left placeholder-sec/30 text-sm" 
                                   placeholder="••••••••">
                            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-sec"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></div>
                        </div>
                    </div>

                    <div class="group md:col-span-2">
                        <label class="block text-sec text-xs font-bold mb-2 uppercase tracking-wider group-focus-within:text-primary transition-colors">تکرار رمز عبور <span class="text-error">*</span></label>
                        <div class="relative">
                            <input type="password" name="confirm_password" required 
                                   class="w-full bg-bg border border-border text-white rounded-xl py-3 px-4 pl-10 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all ltr text-left placeholder-sec/30 text-sm" 
                                   placeholder="••••••••">
                            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-sec"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></div>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Websites Section -->
                <div class="mb-8 p-5 bg-bg/50 border border-border rounded-2xl relative">
                    <div class="absolute -top-3 right-4 bg-card px-2 text-xs text-primary font-bold">وب‌سایت‌های متصل (جهت احراز هویت)</div>
                    
                    <div id="websitesContainer" class="space-y-3">
                        <?php 
                        // نمایش فیلد اول
                        $firstSite = isset($val_websites[0]) ? $val_websites[0] : '';
                        ?>
                        <div class="flex gap-2">
                            <div class="relative flex-grow">
                                <input type="url" name="websites[]" value="<?php echo htmlspecialchars($firstSite); ?>" 
                                       class="w-full bg-bg border border-border text-white rounded-xl py-3 px-4 pl-10 focus:border-primary outline-none ltr text-left placeholder-sec/30 text-sm" 
                                       placeholder="https://mysite.com">
                                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-sec"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg></div>
                            </div>
                        </div>

                        <!-- نمایش فیلدهای اضافی اگر قبلا پر شده باشند -->
                        <?php 
                        if (!empty($val_websites) && count($val_websites) > 1) {
                            for ($i = 1; $i < count($val_websites); $i++) {
                                echo '<div class="flex gap-2 website-field"><div class="relative flex-grow"><input type="url" name="websites[]" value="' . htmlspecialchars($val_websites[$i]) . '" class="w-full bg-bg border border-border text-white rounded-xl py-3 px-4 pl-10 focus:border-primary outline-none ltr text-left placeholder-sec/30 text-sm"><div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-sec"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg></div></div><button type="button" onclick="this.parentElement.remove()" class="bg-error/10 text-error hover:bg-error hover:text-white p-3 rounded-xl transition-colors border border-error/20"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button></div>';
                            }
                        }
                        ?>
                    </div>
                    
                    <button type="button" onclick="addWebsiteField()" class="mt-4 text-xs font-bold text-primary hover:text-white flex items-center gap-1 transition-colors px-3 py-1.5 rounded-lg hover:bg-primary/20 bg-primary/5 border border-primary/10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        افزودن وب‌سایت دیگر
                    </button>
                </div>

                <!-- Terms Checkbox -->
                <div class="mb-8 p-4 rounded-xl bg-bg border border-border flex items-start gap-3 hover:border-primary/30 transition-colors cursor-pointer" onclick="document.getElementById('terms').click()">
                    <div class="flex items-center h-5 mt-0.5">
                        <input id="terms" name="terms" type="checkbox" required class="w-5 h-5 text-primary bg-bg border-border rounded focus:ring-primary focus:ring-offset-bg focus:ring-2 cursor-pointer transition-all">
                    </div>
                    <label for="terms" class="text-sm text-sec cursor-pointer select-none leading-relaxed">
                        من تمام <a href="#" class="text-primary hover:text-white underline decoration-dotted underline-offset-4">قوانین و مقررات</a> رِدی استودیو را مطالعه کرده و با آن‌ها موافقم.
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full bg-primary hover:bg-[#008F85] text-white font-black py-4 rounded-xl shadow-[0_0_20px_rgba(0,176,164,0.3)] hover:shadow-[0_0_30px_rgba(0,176,164,0.5)] transform transition-all active:scale-[0.98] group flex items-center justify-center gap-3 text-lg">
                    <span>تکمیل ثبت‌نام</span>
                    <svg class="w-6 h-6 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </button>

            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 pb-8">
            <p class="text-sec text-sm">
                قبلاً حساب کاربری ساخته‌اید؟ 
                <a href="login.php" class="text-primary font-bold hover:text-white transition-colors underline decoration-dotted underline-offset-4">وارد شوید</a>
            </p>
        </div>

    </div>

    <!-- Scripts -->
    <script>
        function addWebsiteField() {
            const container = document.getElementById('websitesContainer');
            const div = document.createElement('div');
            div.className = 'flex gap-2 website-field mt-3';
            div.innerHTML = `
                <div class="relative flex-grow">
                    <input type="url" name="websites[]" class="w-full bg-bg border border-border text-white rounded-xl py-3 px-4 pl-10 focus:border-primary outline-none ltr text-left placeholder-sec/30 text-sm" placeholder="https://site.com">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-sec"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg></div>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="bg-error/10 text-error hover:bg-error hover:text-white p-3 rounded-xl transition-colors border border-error/20">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            `;
            container.appendChild(div);
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>