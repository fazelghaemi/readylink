<?php
/**
 * RDYS - Enterprise Landing Page
 * Theme: Ready Studio (Teal & Black)
 * Version: 11.0.0 (Final UI)
 */

if (file_exists('functions.php')) {
    require_once 'functions.php';
} else {
    // Fallback logic
    function isLoggedIn() { return false; }
    function isAdmin() { return false; }
    function getCurrentUser() { return null; }
    function getUserDomains($id) { return []; }
    $currentUser = null;
}

$currentUser = isLoggedIn() ? getCurrentUser() : null;

// دریافت دامنه‌ها برای دراپ‌دان
$domains = [['id' => 1, 'domain' => $_SERVER['HTTP_HOST'] ?? 'rdys.ir']];
if (isset($pdo) && $currentUser) {
    try {
        $domains = getUserDomains($currentUser['id']);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>RDYS | کوتاه کننده لینک ردی استودیو</title>
    
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
                        sec: "#9CA3AF", border: "#1F1F1F", success: "#00B0A4", error: "#FF3B30",
                    },
                    fontFamily: { sans: ['ReadyFont', 'Vazirmatn', 'sans-serif'] },
                    animation: { 
                        'float': 'float 6s ease-in-out infinite',
                        'marquee': 'marquee 25s linear infinite',
                        'pulse-glow': 'pulseGlow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite'
                    },
                    keyframes: { 
                        float: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-15px)' } },
                        marquee: { '0%': { transform: 'translateX(100%)' }, '100%': { transform: 'translateX(-100%)' } },
                        pulseGlow: { '0%, 100%': { opacity: 1, boxShadow: '0 0 20px -5px rgba(0, 176, 164, 0.5)' }, '50%': { opacity: .8, boxShadow: '0 0 10px -5px rgba(0, 176, 164, 0.2)' } }
                    }
                }
            }
        }
    </script>
    <style>
        .icon-bg { transition: all 0.3s ease; }
        .feature-card:hover .icon-bg { transform: scale(1.1) rotate(5deg); background-color: rgba(0, 176, 164, 0.15); border-color: var(--primary); }
        .step-circle::after { content: ''; position: absolute; width: 2px; height: 100%; background: linear-gradient(to bottom, var(--primary), transparent); left: 50%; top: 100%; transform: translateX(-50%); z-index: -1; }
        @media (min-width: 768px) {
            .step-circle::after { width: 100%; height: 2px; left: 50%; top: 50%; transform: translateY(-50%); background: linear-gradient(to left, var(--border-color), transparent); }
            .step-last::after { display: none; }
        }
    </style>
</head>
<body class="bg-bg text-text min-h-screen flex flex-col antialiased selection:bg-primary selection:text-white overflow-x-hidden font-sans">

    <!-- Background Elements -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute -top-[10%] -left-[10%] w-[600px] h-[600px] bg-primary/5 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[20%] -right-[10%] w-[500px] h-[500px] bg-purple-900/5 rounded-full blur-[100px]"></div>
        <div class="bg-noise absolute inset-0 opacity-[0.03]"></div>
    </div>

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 w-full glass-panel border-b border-white/5 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Logo -->
                <a href="index.php" class="flex items-center gap-3 group z-10">
                    <img src="favicon-black.png" alt="RDYS" class="w-10 h-10 rounded-xl shadow-[0_0_15px_rgba(0,176,164,0.3)] transition-transform duration-300 group-hover:scale-105">
                    <span class="text-2xl font-black tracking-tighter text-white">ردی لینک | <span class="text-primary">ReadyLink</span></span>
                </a>
                
                <!-- Actions -->
                <div class="flex items-center gap-3 z-10">
                    <?php if ($currentUser): ?>
                        <div class="hidden md:flex flex-col items-end mr-4">
                            <span class="text-[10px] text-sec uppercase tracking-widest">خوش آمدید</span>
                            <span class="text-sm font-bold text-white"><?php echo htmlspecialchars($currentUser['username']); ?></span>
                        </div>
                        <a href="admin.php" class="flex items-center gap-2 bg-card border border-border text-white px-5 py-2.5 rounded-xl transition-all hover:border-primary hover:shadow-glow text-sm font-bold">
                            <span>داشبورد</span>
                            <svg class="w-4 h-4 text-sec group-hover:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 20c0-3.314 3.582-6 8-6s8 2.686 8 6"/></svg>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="hidden md:inline-block text-sec hover:text-white font-bold text-sm transition-colors px-4 py-2">ورود</a>
                        <a href="register.php" class="bg-primary hover:bg-[#008F85] text-white px-6 py-2.5 rounded-xl font-bold text-sm transition-all shadow-[0_0_20px_rgba(0,176,164,0.3)] hover:shadow-[0_0_30px_rgba(0,176,164,0.5)] transform hover:-translate-y-0.5">
                            عضویت رایگان
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <header class="relative pt-24 pb-32 w-full max-w-7xl mx-auto px-4 overflow-visible z-10">
        
        <!-- 3D Assets (Floating) -->
        <div class="absolute top-0 left-[2%] hidden lg:block pointer-events-none select-none animate-float">
            <img src="megaphone_bubble_coming_soon.png" class="w-40 opacity-60 drop-shadow-2xl" alt="Coming Soon">
        </div>
        <div class="absolute bottom-10 right-[2%] hidden lg:block pointer-events-none select-none animate-float" style="animation-delay: 2.5s;">
            <img src="upgrade-account-3d-icon.webp" class="w-36 opacity-50 drop-shadow-2xl" alt="Upgrade">
        </div>

        <div class="w-full max-w-3xl mx-auto text-center animate-fade-in relative z-10">
            <h1 class="text-5xl md:text-7xl font-black mb-6 leading-tight tracking-tight text-white drop-shadow-xl">
                لینک‌های خود را <br class="md:hidden" />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-emerald-400 relative">
                    کوتاه و ماندگار
                    <svg class="absolute w-full h-3 -bottom-2 left-0 text-primary opacity-60" viewBox="0 0 200 9" fill="none"><path d="M2.00025 6.99997C25.7501 2.49994 132.5 -3.50004 198 6.99997" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg>
                </span> کنید
            </h1>
            
            <p class="text-sec text-lg md:text-xl mb-10 max-w-2xl mx-auto leading-relaxed font-medium">
                پلتفرم مدیریت لینک <span class="text-white font-bold border-b-2 border-primary/30 pb-0.5">رِدی استودیو</span>. <br>
                تجربه‌ای متفاوت با اتصال دامنه اختصاصی، آمار دقیق و API قدرتمند.
            </p>

            <!-- Shortener Box -->
            <div class="rdys-card p-3 md:p-4 rounded-3xl relative transition-all duration-500 hover:shadow-glow hover:border-primary/50 bg-card/80 backdrop-blur-xl border border-border">
                <form id="shortenForm" class="flex flex-col md:flex-row items-stretch gap-3" novalidate>
                    
                    <!-- Domain Select -->
                    <div class="relative min-w-[170px] group/select">
                        <select name="domain_id" <?php echo !$currentUser ? 'disabled' : ''; ?> class="w-full h-full bg-bg text-white border border-border rounded-2xl px-4 py-4 md:py-0 pl-10 appearance-none outline-none focus:border-primary cursor-pointer font-bold text-sm transition-colors ltr text-center md:text-left disabled:opacity-50 disabled:cursor-not-allowed">
                            <?php foreach($domains as $domain): ?>
                                <option value="<?php echo htmlspecialchars($domain['id']); ?>"><?php echo htmlspecialchars($domain['domain']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-primary group-hover/select:scale-110 transition-transform">
                            <img src="3d-globe.png" class="w-5 h-5 opacity-90 drop-shadow-md" onerror="this.style.display='none'">
                        </div>
                    </div>

                    <!-- URL Input -->
                    <input type="url" name="url" id="urlInput" placeholder="لینک طولانی را اینجا وارد کنید..." 
                           class="flex-grow bg-bg text-white placeholder-sec/50 px-6 py-4 rounded-2xl text-base outline-none border border-border focus:border-primary focus:ring-1 focus:ring-primary w-full dir-ltr font-mono shadow-inner transition-all" required autocomplete="off">

                    <!-- Submit Button -->
                    <?php if ($currentUser): ?>
                        <button type="submit" id="submitBtn" class="bg-primary hover:bg-[#008F85] text-white font-black rounded-2xl px-10 py-4 md:py-0 transition-all active:scale-95 flex items-center justify-center gap-2 min-w-[140px] shadow-[0_0_20px_rgba(0,176,164,0.3)] hover:shadow-[0_0_30px_rgba(0,176,164,0.5)] group">
                            <span class="whitespace-nowrap text-lg">کوتاه کن</span>
                            <img src="loading-hub.webp" id="btnLoader" class="w-6 h-6 hidden animate-spin" alt="Loading">
                        </button>
                    <?php else: ?>
                        <button type="button" onclick="window.location.href='login.php?redirect=index.php'" class="bg-card border-2 border-primary text-primary hover:bg-primary hover:text-white font-black rounded-2xl px-8 py-4 md:py-0 transition-all active:scale-95 flex items-center justify-center gap-2 min-w-[180px] shadow-lg group">
                            <svg class="w-5 h-5 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                            <span class="whitespace-nowrap text-sm">ورود و ساخت لینک</span>
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Advanced Options -->
            <div class="mt-6 flex flex-col items-center">
                <button type="button" onclick="toggleAdvanced()" class="text-sec text-sm font-bold hover:text-white flex items-center gap-2 transition-colors py-2 px-4 rounded-full hover:bg-card border border-transparent hover:border-border">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <span>تنظیمات پیشرفته (نام مستعار)</span>
                </button>
                <div id="advancedOptions" class="hidden w-full max-w-sm mt-4 animate-fade-in">
                    <div class="relative group">
                        <input type="text" id="customAlias" placeholder="مثلاً: my-offer" class="w-full bg-bg border border-border rounded-xl px-5 py-3 text-white text-sm focus:border-primary outline-none text-center ltr placeholder-sec/30 transition-all group-hover:border-primary/50">
                        <div class="absolute right-4 top-3 text-[10px] text-primary font-bold bg-bg px-2 rounded">اختیاری</div>
                    </div>
                </div>
            </div>

            <!-- Result Box -->
            <div id="resultContainer" class="hidden mt-10 w-full animate-fade-in">
                <div class="rdys-card p-6 rounded-3xl relative overflow-hidden border-primary/30 shadow-[0_0_40px_-10px_rgba(0,176,164,0.3)]">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-primary to-transparent opacity-80"></div>
                    <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                        <div class="flex-grow text-center md:text-right w-full">
                            <p class="text-sec text-xs mb-2 uppercase tracking-widest font-bold">لینک کوتاه شما آماده است</p>
                            <div class="bg-bg p-4 rounded-2xl border border-border group hover:border-primary/50 transition-colors relative cursor-pointer" onclick="copyToClipboard()">
                                <a id="shortUrlDisplay" href="#" target="_blank" class="text-2xl font-mono text-primary font-bold hover:text-white transition-colors truncate dir-ltr block select-all"></a>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <button onclick="copyToClipboard()" class="flex flex-col items-center justify-center w-20 h-20 bg-card border border-border hover:border-primary hover:bg-bg hover:text-primary rounded-2xl transition-all group" title="کپی">
                                <img src="3d-link.png" class="w-8 h-8 mb-1" onerror="this.style.display='none'">
                                <span class="text-[10px] font-bold uppercase tracking-wider">کپی</span>
                            </button>
                            <a id="qrDownloadLink" href="#" target="_blank" class="flex flex-col items-center justify-center w-20 h-20 bg-card border border-border hover:border-white hover:bg-bg hover:text-white rounded-2xl transition-all group" title="QR Code">
                                <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4h2v-4zM6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span class="text-[10px] font-bold uppercase tracking-wider">QR</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="errorContainer" class="hidden mt-8 bg-error/10 border border-error/30 text-error px-6 py-4 rounded-2xl text-sm font-bold animate-fade-in flex items-center justify-center gap-3">
                <img src="block-vip-access.webp" class="w-6 h-6" onerror="this.style.display='none'">
                <span id="errorText">خطا</span>
            </div>
        </div>
    </header>

    <!-- HOT UPDATES TICKER -->
    <section class="py-10 bg-bg border-y border-border/30 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row items-center gap-6">
            <div class="flex items-center gap-4 min-w-max z-10 bg-bg p-2 rounded-xl">
                <div class="w-12 h-12 bg-gradient-to-tr from-primary to-card rounded-xl flex items-center justify-center border border-primary/20 shadow-lg">
                    <img src="3d-fire.png" class="w-8 h-8 animate-pulse drop-shadow-md" alt="Fire" onerror="this.style.display='none'">
                </div>
                <div>
                    <h3 class="text-white font-bold text-lg">تازه‌های داغ</h3>
                    <p class="text-sec text-xs">آخرین تغییرات سیستم</p>
                </div>
            </div>
            <div class="flex-grow w-full bg-card/50 border border-border rounded-2xl p-3 overflow-hidden relative shadow-inner">
                <div class="whitespace-nowrap animate-marquee inline-block text-sm text-sec font-medium">
                    <span class="mx-8 flex items-center inline-flex"><span class="w-2 h-2 bg-primary rounded-full mr-2 ml-1"></span> ✨ نسخه جدید ۱۰.۰ منتشر شد!</span>
                    <span class="mx-8 flex items-center inline-flex"><span class="w-2 h-2 bg-purple-500 rounded-full mr-2 ml-1"></span> 🚀 اضافه شدن API قدرتمند</span>
                    <span class="mx-8 flex items-center inline-flex"><span class="w-2 h-2 bg-yellow-500 rounded-full mr-2 ml-1"></span> 🌐 اتصال دامنه اختصاصی</span>
                    <span class="mx-8 flex items-center inline-flex"><span class="w-2 h-2 bg-green-500 rounded-full mr-2 ml-1"></span> 💎 داشبورد مدیریت جدید</span>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section class="py-24 bg-card relative overflow-hidden">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9IiMzMzMiIGZpbGwtb3BhY2l0eT0iMC4yIi8+PC9zdmc+')] opacity-20"></div>
        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-black text-white mb-4">چرا ردی استودیو؟</h2>
                <p class="text-sec text-lg max-w-2xl mx-auto">ابزارهایی برای حرفه‌ای‌تر شدن کسب‌وکار شما.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Feature Cards -->
                <div class="feature-card p-8 rounded-3xl bg-bg border border-border hover:border-primary/50 transition-all duration-300 group hover:-translate-y-2">
                    <div class="icon-bg w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mb-6 transition-all duration-300 border border-primary/20">
                        <img src="3d-globe.png" class="w-8 h-8 drop-shadow-md" alt="Domain">
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 group-hover:text-primary transition-colors">دامنه اختصاصی</h3>
                    <p class="text-sec text-sm leading-relaxed">لینک‌های کوتاه را با دامنه شخصی خودتان بسازید.</p>
                </div>
                <div class="feature-card p-8 rounded-3xl bg-bg border border-border hover:border-primary/50 transition-all duration-300 group hover:-translate-y-2">
                    <div class="icon-bg w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mb-6 transition-all duration-300 border border-primary/20">
                        <img src="3d-chart.png" class="w-8 h-8 drop-shadow-md" alt="Stats">
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 group-hover:text-primary transition-colors">آمار پیشرفته</h3>
                    <p class="text-sec text-sm leading-relaxed">رصد دقیق تعداد کلیک‌ها و رفتار کاربران.</p>
                </div>
                <div class="feature-card p-8 rounded-3xl bg-bg border border-border hover:border-primary/50 transition-all duration-300 group hover:-translate-y-2">
                    <div class="icon-bg w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mb-6 transition-all duration-300 border border-primary/20">
                        <img src="3d-chains.png" class="w-8 h-8 drop-shadow-md" alt="API">
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 group-hover:text-primary transition-colors">API قدرتمند</h3>
                    <p class="text-sec text-sm leading-relaxed">اتصال سیستم به نرم‌افزارها و سایت‌های خودتان.</p>
                </div>
                <div class="feature-card p-8 rounded-3xl bg-bg border border-border hover:border-primary/50 transition-all duration-300 group hover:-translate-y-2">
                    <div class="icon-bg w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mb-6 transition-all duration-300 border border-primary/20">
                        <img src="3d-rocket.png" class="w-8 h-8 drop-shadow-md" alt="Speed">
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 group-hover:text-primary transition-colors">سرعت و امنیت</h3>
                    <p class="text-sec text-sm leading-relaxed">تضمین آپتایم بالا و امنیت لینک‌ها.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="py-24 bg-bg relative">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-black text-white mb-4">چطور کار می‌کند؟</h2>
                <p class="text-sec">تنها ۳ قدم ساده</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 relative px-4">
                <div class="text-center relative z-10 step-circle step-line">
                    <div class="w-20 h-20 mx-auto bg-card border-2 border-primary rounded-full flex items-center justify-center text-3xl font-black text-primary mb-6 shadow-glow">۱</div>
                    <h3 class="text-xl font-bold text-white mb-2">ثبت‌نام کنید</h3>
                    <p class="text-sec text-sm">حساب کاربری رایگان بسازید.</p>
                </div>
                <div class="text-center relative z-10 step-circle step-line">
                    <div class="w-20 h-20 mx-auto bg-card border-2 border-primary rounded-full flex items-center justify-center text-3xl font-black text-primary mb-6 shadow-glow">۲</div>
                    <h3 class="text-xl font-bold text-white mb-2">لینک بسازید</h3>
                    <p class="text-sec text-sm">لینک طولانی و دامنه را وارد کنید.</p>
                </div>
                <div class="text-center relative z-10 step-circle step-last">
                    <div class="w-20 h-20 mx-auto bg-card border-2 border-primary rounded-full flex items-center justify-center text-3xl font-black text-primary mb-6 shadow-glow">۳</div>
                    <h3 class="text-xl font-bold text-white mb-2">به اشتراک بگذارید</h3>
                    <p class="text-sec text-sm">لینک کوتاه را کپی و آمار آن را بررسی کنید.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CALL TO ACTION -->
    <section class="py-32 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-t from-primary/5 to-bg pointer-events-none"></div>
        <div class="max-w-4xl mx-auto px-6 text-center relative z-10">
            <h2 class="text-4xl md:text-6xl font-black text-white mb-8">آماده شروع هستید؟</h2>
            <a href="register.php" class="inline-flex items-center gap-3 bg-primary hover:bg-[#008F85] text-white font-black text-lg py-5 px-12 rounded-2xl shadow-[0_0_40px_rgba(0,176,164,0.4)] hover:shadow-[0_0_60px_rgba(0,176,164,0.6)] transition-all transform hover:scale-105 active:scale-95 group">
                شروع رایگان
                <svg class="w-6 h-6 transition-transform group-hover:translate-x-[-5px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
            </a>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="w-full border-t border-border bg-card pt-16 pb-8 z-20 relative">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
                <div class="col-span-1 md:col-span-2">
                    <a href="index.php" class="flex items-center gap-3 mb-6">
                        <img src="favicon-black.png" class="w-8 h-8 rounded-lg shadow-sm">
                        <span class="text-2xl font-black text-white">ردی لینک | <span class="text-primary">ReadyLink</span></span>
                        </a>
                    <p class="text-sec text-sm leading-relaxed max-w-xs">ارائه‌دهنده راهکارهای نوین نرم‌افزاری و ابزارهای دیجیتال مارکتینگ.</p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-6 border-b border-primary/30 inline-block pb-2">لینک‌های مفید</h4>
                    <ul class="space-y-4 text-sm text-sec">
                        <li><a href="register.php" class="hover:text-primary transition-colors">عضویت</a></li>
                        <li><a href="login.php" class="hover:text-primary transition-colors">ورود</a></li>
                        <li><a href="admin.php" class="hover:text-primary transition-colors">داشبورد</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-6 border-b border-primary/30 inline-block pb-2">اطلاعات قانونی</h4>
                    <ul class="space-y-4 text-sm text-sec">
                        <li><a href="https://readystudio.ir/rdys-linkshortener/#rules" target="_blank" class="hover:text-primary transition-colors">قوانین و مقررات</a></li>
                        <li><a href="https://readystudio.ir/rdys-linkshortener/#price" target="_blank" class="hover:text-primary transition-colors">تعرفه‌ها</a></li>
                        <li><a href="https://readystudio.ir/contact-us" target="_blank" class="hover:text-primary transition-colors">پشتیبانی</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-border pt-8 text-center"><p class="text-sec text-xs font-mono">&copy; <?php echo date('Y'); ?> Ready Studio. All rights reserved.</p></div>
        </div>
    </footer>

    <!-- Toast -->
    <div id="toast" class="fixed bottom-8 left-1/2 transform -translate-x-1/2 bg-white text-bg px-8 py-4 rounded-full shadow-glow translate-y-32 opacity-0 transition-all duration-500 font-black flex items-center gap-3 z-[60] border-2 border-primary">
        <img src="3d-rocket.png" class="w-6 h-6" onerror="this.style.display='none'">
        لینک کپی شد!
    </div>

    <!-- Scripts -->
    <script>
        // PWA & Service Worker
        if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('sw.js')); }

        const form = document.getElementById('shortenForm');
        const urlInput = document.getElementById('urlInput');
        const customAlias = document.getElementById('customAlias');
        const submitBtn = document.getElementById('submitBtn');
        const btnLoader = document.getElementById('btnLoader');
        const resultContainer = document.getElementById('resultContainer');
        const errorContainer = document.getElementById('errorContainer');
        const errorText = document.getElementById('errorText');
        const shortUrlDisplay = document.getElementById('shortUrlDisplay');
        const qrDownloadLink = document.getElementById('qrDownloadLink');

        function toggleAdvanced() {
            const adv = document.getElementById('advancedOptions');
            adv.classList.toggle('hidden');
            if(!adv.classList.contains('hidden')) document.getElementById('customAlias').focus();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!urlInput.value.trim()) { showError('لطفا آدرس لینک را وارد کنید.'); urlInput.focus(); return; }
            setLoading(true); hideError(); resultContainer.classList.add('hidden');
            
            try {
                const formData = new FormData(form);
                formData.append('action', 'shorten');
                if(customAlias.value.trim()) formData.append('alias', customAlias.value.trim());
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                
                if (response.status === 401) {
                    window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                    return;
                }

                const data = await response.json();
                
                if (data.success) { 
                    showResult(data.data.short_url); 
                    urlInput.value = ''; 
                    customAlias.value = ''; 
                } else { 
                    showError(data.message || 'خطای سرور'); 
                }
            } catch (error) { showError('خطا در ارتباط.'); } finally { setLoading(false); }
        });

        function showResult(url) {
            const cleanUrl = url.replace(/(^\w+:|^)\/\//, '');
            shortUrlDisplay.href = url; 
            shortUrlDisplay.textContent = cleanUrl;
            qrDownloadLink.href = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(url)}`;
            resultContainer.classList.remove('hidden'); 
            resultContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function showError(msg) { errorText.textContent = msg; errorContainer.classList.remove('hidden'); errorContainer.classList.add('animate-pulse'); setTimeout(() => errorContainer.classList.remove('animate-pulse'), 500); }
        function hideError() { errorContainer.classList.add('hidden'); }
        function setLoading(isLoading) {
            if (isLoading) { 
                submitBtn.disabled = true; submitBtn.classList.add('opacity-75', 'cursor-not-allowed'); 
                btnLoader.classList.remove('hidden'); 
                const btnText = submitBtn.querySelector('span'); if(btnText) btnText.style.display = 'none';
            } else { 
                submitBtn.disabled = false; submitBtn.classList.remove('opacity-75', 'cursor-not-allowed'); 
                btnLoader.classList.add('hidden'); 
                const btnText = submitBtn.querySelector('span'); if(btnText) btnText.style.display = 'inline';
            }
        }

        function copyToClipboard() {
            const url = shortUrlDisplay.href;
            if (navigator.clipboard && window.isSecureContext) { 
                navigator.clipboard.writeText(url).then(showToast); 
            } else {
                const textArea = document.createElement("textarea"); 
                textArea.value = url; textArea.style.position = "fixed"; textArea.style.left = "-9999px";
                document.body.appendChild(textArea); textArea.focus(); textArea.select();
                try { document.execCommand('copy'); showToast(); } catch (err) {}
                document.body.removeChild(textArea);
            }
        }

        function showToast() { 
            const toast = document.getElementById('toast'); 
            toast.classList.remove('translate-y-32', 'opacity-0'); 
            setTimeout(() => { toast.classList.add('translate-y-32', 'opacity-0'); }, 3000); 
        }
    </script>
</body>
</html>