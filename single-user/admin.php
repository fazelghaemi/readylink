<?php
/**
 * RDYS - Advanced Admin Dashboard
 * Theme: Ready Studio (Teal & Black)
 * Version: 4.0.0
 * * Features: Real-time stats, Domain management, Link controls, Chart.js integration.
 */

require_once 'functions.php';

// 1. بررسی سطوح دسترسی و امنیت
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// 2. خروج از حساب کاربری
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// 3. دریافت داده‌های داشبورد
try {
    // آمار کلی
    $totalLinks = $pdo->query("SELECT COUNT(*) FROM links")->fetchColumn();
    $totalViews = $pdo->query("SELECT COUNT(*) FROM stats")->fetchColumn();
    $activeDomains = $pdo->query("SELECT COUNT(*) FROM domains WHERE is_active = 1")->fetchColumn();
    
    // دریافت لیست لینک‌ها (۵۰ مورد اخیر) با اطلاعات دامنه
    $stmt = $pdo->prepare("
        SELECT 
            l.id, l.long_url, l.short_code, l.created_at, l.views,
            d.domain
        FROM links l
        LEFT JOIN domains d ON l.domain_id = d.id
        ORDER BY l.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute();
    $recentLinks = $stmt->fetchAll();

    // دریافت لیست دامنه‌ها
    $domainsList = $pdo->query("SELECT * FROM domains ORDER BY id DESC")->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری داده‌های مدیریت: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>پنل مدیریت | RDYS</title>
    
    <!-- استایل مرکزی -->
    <link rel="stylesheet" href="style.css">
    
    <!-- فونت آیکون‌ها (اختیاری، اما برای زیبایی بیشتر از SVG داخلی استفاده می‌کنیم) -->
    
    <!-- کتابخانه‌ها -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
                        error: "#FF3B30" 
                    },
                    fontFamily: {
                        sans: ['ReadyFont', 'Vazirmatn', 'sans-serif'],
                        mono: ['ui-monospace', 'monospace']
                    }
                }
            }
        }
    </script>
    <style>
        /* تنظیمات اختصاصی پنل */
        .sidebar-active {
            background: linear-gradient(90deg, rgba(0, 176, 164, 0.1) 0%, transparent 100%);
            border-right: 3px solid var(--primary);
            color: var(--primary);
        }
        .table-row-hover:hover td {
            background-color: rgba(255, 255, 255, 0.02);
        }
    </style>
</head>
<body class="h-screen flex overflow-hidden bg-bg text-text font-sans selection:bg-primary selection:text-white">
    
    <!-- Sidebar (نوار کناری) -->
    <aside class="hidden md:flex flex-col w-72 bg-card border-l border-border h-full flex-shrink-0 z-30 shadow-2xl transition-all duration-300">
        
        <!-- Header Sidebar -->
        <div class="p-6 flex items-center gap-4 border-b border-border/50">
            <div class="relative group">
                <div class="absolute -inset-1 bg-gradient-to-r from-primary to-emerald-600 rounded-full blur opacity-25 group-hover:opacity-75 transition duration-1000 group-hover:duration-200"></div>
                <div class="relative w-12 h-12 rounded-full flex items-center justify-center bg-bg border border-border text-primary font-black text-xl shadow-inner">
                    R
                </div>
            </div>
            <div>
                <h1 class="font-black text-xl tracking-tight text-white">رِدی ادمین</h1>
                <p class="text-[10px] text-sec uppercase tracking-widest font-bold">نسخه حرفه‌ای 4.0</p>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="flex-grow p-4 space-y-2 overflow-y-auto custom-scrollbar">
            <p class="px-4 text-xs font-bold text-sec/50 uppercase tracking-wider mb-2 mt-2">منوی اصلی</p>
            
            <button onclick="switchTab('dashboard')" id="nav-dashboard" class="sidebar-active w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sec hover:bg-white/5 hover:text-white transition-all text-sm font-bold group">
                <svg class="w-5 h-5 group-hover:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span>داشبورد و آمار</span>
            </button>
            
            <button onclick="switchTab('domains')" id="nav-domains" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sec hover:bg-white/5 hover:text-white transition-all text-sm font-bold group">
                <svg class="w-5 h-5 group-hover:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                <span>مدیریت دامنه‌ها</span>
            </button>

            <p class="px-4 text-xs font-bold text-sec/50 uppercase tracking-wider mb-2 mt-6">دسترسی سریع</p>

            <a href="index.php" target="_blank" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sec hover:bg-white/5 hover:text-white transition-all text-sm font-bold group">
                <svg class="w-5 h-5 group-hover:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                <span>مشاهده سایت</span>
            </a>
            
            <a href="export.php" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sec hover:bg-white/5 hover:text-white transition-all text-sm font-bold group">
                <svg class="w-5 h-5 group-hover:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                <span>خروجی اکسل (CSV)</span>
            </a>
        </nav>
        
        <!-- User Profile -->
        <div class="p-4 border-t border-border/50 bg-bg/50">
            <div class="flex items-center gap-3 mb-4 px-2">
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-primary to-card flex items-center justify-center border border-border shadow-lg">
                    <span class="font-black text-white">A</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></p>
                    <p class="text-[10px] text-primary">مدیر کل سیستم</p>
                </div>
            </div>
            <a href="?logout=true" class="flex items-center justify-center gap-2 w-full bg-error/10 hover:bg-error/20 text-error border border-error/20 font-bold py-2.5 rounded-xl transition-all text-sm group">
                <svg class="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                خروج امن
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-grow h-full overflow-y-auto relative w-full bg-bg custom-scrollbar">
        
        <!-- Mobile Header (Visible only on mobile) -->
        <header class="md:hidden flex items-center justify-between p-4 bg-card/80 backdrop-blur-md border-b border-border sticky top-0 z-30">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center shadow-glow"><span class="font-bold text-white text-xs">R</span></div>
                <h1 class="font-black text-lg text-white">RDYS</h1>
            </div>
            <div class="flex gap-3">
                <a href="index.php" class="p-2 text-sec hover:text-white bg-bg rounded-lg border border-border"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg></a>
                <a href="?logout=true" class="p-2 text-error hover:bg-error/10 bg-bg rounded-lg border border-border"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg></a>
            </div>
        </header>

        <!-- Mobile Tab Navigation -->
        <div class="md:hidden flex border-b border-border bg-card sticky top-[65px] z-20">
            <button onclick="switchTab('dashboard')" class="flex-1 py-4 text-sm font-bold text-center border-b-2 border-primary text-primary transition-colors" id="mob-dashboard">داشبورد</button>
            <button onclick="switchTab('domains')" class="flex-1 py-4 text-sm font-bold text-center border-b-2 border-transparent text-sec hover:text-white transition-colors" id="mob-domains">دامنه‌ها</button>
        </div>

        <div class="p-6 md:p-10 max-w-7xl mx-auto pb-24">
            
            <!-- SECTION 1: DASHBOARD -->
            <div id="tab-dashboard" class="animate-fade-in space-y-8">
                
                <!-- Welcome Banner -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h2 class="text-3xl font-black text-white tracking-tight mb-1">خوش آمدید، مدیر 👋</h2>
                        <p class="text-sec text-sm">گزارش عملکرد سیستم در یک نگاه</p>
                    </div>
                    <div class="text-right hidden md:block">
                        <p class="text-xs text-primary font-bold bg-primary/10 px-3 py-1 rounded-full border border-primary/20">
                            امروز: <?php echo date('Y/m/d'); ?>
                        </p>
                    </div>
                </div>

                <!-- Stats Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- Card 1 -->
                    <div class="bg-card border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-primary/50 transition-all duration-300 shadow-lg">
                        <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                            <svg class="w-24 h-24 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                        </div>
                        <p class="text-sec text-xs font-bold uppercase tracking-widest mb-2">کل لینک‌های فعال</p>
                        <p class="text-4xl font-black text-white group-hover:text-primary transition-colors"><?php echo number_format($totalLinks); ?></p>
                    </div>
                    
                    <!-- Card 2 -->
                    <div class="bg-card border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-primary/50 transition-all duration-300 shadow-lg">
                        <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                            <svg class="w-24 h-24 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </div>
                        <p class="text-sec text-xs font-bold uppercase tracking-widest mb-2">مجموع بازدیدها</p>
                        <p class="text-4xl font-black text-white group-hover:text-primary transition-colors"><?php echo number_format($totalViews); ?></p>
                    </div>

                    <!-- Card 3 -->
                    <div class="bg-card border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-primary/50 transition-all duration-300 shadow-lg">
                        <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                            <svg class="w-24 h-24 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                        </div>
                        <p class="text-sec text-xs font-bold uppercase tracking-widest mb-2">دامنه‌های متصل</p>
                        <p class="text-4xl font-black text-white group-hover:text-primary transition-colors"><?php echo number_format($activeDomains); ?></p>
                    </div>
                </div>

                <!-- Recent Links Table -->
                <div class="bg-card border border-border rounded-3xl overflow-hidden shadow-2xl relative">
                    <div class="p-6 border-b border-border flex justify-between items-center bg-card/95 backdrop-blur-xl sticky top-0 z-10">
                        <h2 class="font-black text-lg text-white flex items-center gap-2">
                            <span class="w-2 h-6 bg-primary rounded-full"></span>
                            آخرین لینک‌های ساخته شده
                        </h2>
                        <span class="text-xs font-bold text-bg bg-primary px-3 py-1 rounded-lg">50 مورد اخیر</span>
                    </div>
                    
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-right text-sm">
                            <thead class="bg-bg text-sec border-b border-border/50 uppercase text-xs tracking-wider">
                                <tr>
                                    <th class="p-5 font-bold">لینک کوتاه</th>
                                    <th class="p-5 font-bold">لینک مقصد</th>
                                    <th class="p-5 font-bold text-center">کلیک</th>
                                    <th class="p-5 font-bold text-center">زمان</th>
                                    <th class="p-5 font-bold text-center">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/50 text-white">
                                <?php if (count($recentLinks) > 0): ?>
                                    <?php foreach ($recentLinks as $link): ?>
                                    <tr class="table-row-hover transition-colors group">
                                        <td class="p-5 ltr text-left">
                                            <div class="flex flex-col">
                                                <a href="<?php echo 'http://' . $link['domain'] . '/' . $link['short_code']; ?>" target="_blank" class="text-primary font-mono font-bold hover:text-white transition-colors text-base">
                                                    /<?php echo htmlspecialchars($link['short_code']); ?>
                                                </a>
                                                <span class="text-[10px] text-sec mt-1 font-mono opacity-60"><?php echo htmlspecialchars($link['domain']); ?></span>
                                            </div>
                                        </td>
                                        <td class="p-5 max-w-xs" title="<?php echo htmlspecialchars($link['long_url']); ?>">
                                            <div class="truncate dir-ltr font-mono text-sec group-hover:text-white transition-colors bg-bg/50 px-3 py-1.5 rounded-lg border border-transparent group-hover:border-border">
                                                <?php echo htmlspecialchars($link['long_url']); ?>
                                            </div>
                                        </td>
                                        <td class="p-5 text-center">
                                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-primary/10 text-primary border border-primary/20 group-hover:bg-primary group-hover:text-bg transition-colors">
                                                <?php echo number_format($link['views']); ?>
                                            </span>
                                        </td>
                                        <td class="p-5 text-center text-sec text-xs font-bold whitespace-nowrap">
                                            <?php echo timeAgo($link['created_at']); ?>
                                        </td>
                                        <td class="p-5 text-center whitespace-nowrap">
                                            <div class="flex items-center justify-center gap-2 opacity-100 lg:opacity-60 lg:group-hover:opacity-100 transition-all">
                                                <button onclick="openStatsModal(<?php echo $link['id']; ?>)" class="p-2.5 text-primary bg-primary/5 hover:bg-primary hover:text-bg rounded-xl border border-primary/20 transition-all" title="نمودار و آمار">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                                </button>
                                                <button onclick="deleteLink(<?php echo $link['id']; ?>, this)" class="p-2.5 text-error bg-error/5 hover:bg-error hover:text-white rounded-xl border border-error/20 transition-all" title="حذف دائمی">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="p-12 text-center text-sec">
                                            <div class="flex flex-col items-center gap-4">
                                                <svg class="w-16 h-16 text-border" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                                <p>هنوز هیچ لینکی ساخته نشده است.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: DOMAINS -->
            <div id="tab-domains" class="hidden animate-fade-in">
                <div class="bg-card rounded-3xl border border-border p-8 max-w-4xl mx-auto shadow-2xl relative overflow-hidden">
                    
                    <!-- Decorative BG -->
                    <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full blur-[80px] pointer-events-none"></div>

                    <div class="flex items-center gap-3 mb-8 border-b border-border pb-6 relative z-10">
                        <div class="p-3 bg-primary/10 rounded-xl text-primary border border-primary/20">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black text-white">مدیریت دامنه‌ها</h2>
                            <p class="text-sec text-sm">افزودن دامنه‌های پارک شده برای کوتاه‌سازی اختصاصی</p>
                        </div>
                    </div>
                    
                    <!-- Add Domain Form -->
                    <form onsubmit="addDomain(event)" class="flex flex-col md:flex-row gap-4 mb-10 bg-bg p-6 rounded-2xl border border-border shadow-inner relative z-10">
                        <div class="flex-grow relative group">
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-sec group-focus-within:text-primary transition-colors">
                                <span class="font-bold text-sm">https://</span>
                            </div>
                            <input type="text" name="domain" placeholder="example.com" required 
                                   class="w-full bg-card border border-border text-white px-4 py-4 pr-20 rounded-xl focus:border-primary outline-none ltr placeholder-sec/30 font-mono transition-all">
                        </div>
                        <button type="submit" class="bg-primary hover:bg-[#008F85] text-white font-black py-4 px-8 rounded-xl transition-all shadow-[0_0_20px_rgba(0,176,164,0.3)] hover:shadow-[0_0_30px_rgba(0,176,164,0.5)] transform active:scale-95 whitespace-nowrap flex items-center gap-2 justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            افزودن دامنه
                        </button>
                    </form>

                    <!-- Domain List -->
                    <div class="space-y-4 relative z-10">
                        <?php foreach ($domainsList as $domain): ?>
                        <div class="flex items-center justify-between p-5 bg-bg/50 backdrop-blur-sm rounded-2xl border border-border hover:border-primary/40 transition-all group">
                            <div class="flex items-center gap-4">
                                <div class="w-3 h-3 rounded-full <?php echo $domain['is_active'] ? 'bg-primary shadow-[0_0_10px_#00B0A4]' : 'bg-error shadow-[0_0_10px_#FF3B30]'; ?>"></div>
                                <span class="font-mono text-xl font-bold ltr text-white group-hover:text-primary transition-colors tracking-wide"><?php echo htmlspecialchars($domain['domain']); ?></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold text-sec uppercase tracking-wider hidden sm:inline-block">وضعیت:</span>
                                <button onclick="toggleDomain(<?php echo $domain['id']; ?>)" class="<?php echo $domain['is_active'] ? 'text-primary border-primary/30 bg-primary/5' : 'text-sec border-border bg-card'; ?> text-xs font-bold border px-4 py-2 rounded-lg hover:bg-white hover:text-bg hover:border-white transition-all">
                                    <?php echo $domain['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Stats Modal -->
    <div id="statsModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/90 backdrop-blur-md p-4 animate-fade-in">
        <div class="bg-card w-full max-w-5xl rounded-3xl border border-border shadow-2xl overflow-hidden relative flex flex-col max-h-[90vh]">
            
            <!-- Modal Header -->
            <div class="p-6 border-b border-border flex justify-between items-center bg-bg">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-primary/10 rounded-lg text-primary"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg></div>
                    <h3 class="text-xl font-black text-white">آنالیز دقیق بازدیدها</h3>
                </div>
                <button onclick="closeStatsModal()" class="text-sec hover:text-error transition p-2 hover:bg-error/10 rounded-lg"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            
            <div class="p-8 overflow-y-auto flex-grow custom-scrollbar">
                <!-- Chart Area -->
                <div class="h-[350px] w-full mb-10 relative bg-bg p-4 rounded-2xl border border-border">
                    <canvas id="clicksChart"></canvas>
                </div>
                
                <!-- Browser Stats -->
                <div>
                    <h4 class="font-bold text-white text-sm uppercase mb-6 flex items-center gap-2">
                        <span class="w-1.5 h-4 bg-primary rounded-full"></span>
                        مرورگرهای برتر
                    </h4>
                    <div id="browserStatsList" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <!-- Filled by JS dynamically -->
                    </div>
                </div>
            </div>
            
            <!-- Loader -->
            <div id="chartLoader" class="absolute inset-0 bg-bg/95 flex flex-col items-center justify-center z-10 hidden">
                <div class="w-12 h-12 border-4 border-border border-t-primary rounded-full animate-spin mb-4"></div>
                <span class="text-primary text-sm font-bold animate-pulse">در حال دریافت اطلاعات...</span>
            </div>
        </div>
    </div>

    <!-- JavaScript Logic -->
    <script>
        // Tab Management
        function switchTab(tabName) {
            document.getElementById('tab-dashboard').classList.add('hidden');
            document.getElementById('tab-domains').classList.add('hidden');
            document.getElementById('tab-' + tabName).classList.remove('hidden');
            
            // Sidebar Active State
            document.getElementById('nav-dashboard').classList.remove('sidebar-active');
            document.getElementById('nav-domains').classList.remove('sidebar-active');
            
            // Mobile Active State (Borders)
            const mobDash = document.getElementById('mob-dashboard');
            const mobDom = document.getElementById('mob-domains');
            
            if (tabName === 'dashboard') {
                document.getElementById('nav-dashboard').classList.add('sidebar-active');
                if(mobDash) {
                    mobDash.classList.add('border-primary', 'text-primary');
                    mobDash.classList.remove('border-transparent', 'text-sec');
                    mobDom.classList.remove('border-primary', 'text-primary');
                    mobDom.classList.add('border-transparent', 'text-sec');
                }
            } else {
                document.getElementById('nav-domains').classList.add('sidebar-active');
                if(mobDom) {
                    mobDom.classList.add('border-primary', 'text-primary');
                    mobDom.classList.remove('border-transparent', 'text-sec');
                    mobDash.classList.remove('border-primary', 'text-primary');
                    mobDash.classList.add('border-transparent', 'text-sec');
                }
            }
        }

        // Delete Link Action
        async function deleteLink(id, btn) {
            if (!confirm('آیا از حذف این لینک اطمینان دارید؟ تمامی آمار مربوطه نیز حذف خواهد شد.')) return;
            try {
                const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
                const res = await fetch('api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    const row = btn.closest('tr');
                    row.style.transform = 'scale(0.95)';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                } else alert(data.message);
            } catch (e) { alert('خطای شبکه'); }
        }

        // Add Domain Action
        async function addDomain(e) {
            e.preventDefault();
            const fd = new FormData(e.target); fd.append('action', 'add_domain');
            try {
                const res = await fetch('api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) location.reload(); else alert(data.message);
            } catch (e) { alert('خطای شبکه'); }
        }

        // Toggle Domain Action
        async function toggleDomain(id) {
            const fd = new FormData(); fd.append('action', 'toggle_domain'); fd.append('id', id);
            await fetch('api.php', { method: 'POST', body: fd });
            location.reload();
        }

        // Chart & Stats
        let chartInstance = null;

        async function openStatsModal(id) {
            document.getElementById('statsModal').classList.remove('hidden');
            document.getElementById('chartLoader').classList.remove('hidden');
            
            try {
                const fd = new FormData(); fd.append('action', 'get_stats'); fd.append('id', id);
                const res = await fetch('api.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    renderChart(data.daily);
                    renderBrowsers(data.browsers);
                } else {
                    alert(data.message); closeStatsModal();
                }
            } catch(e) { alert('خطا در دریافت آمار'); closeStatsModal(); } 
            finally { document.getElementById('chartLoader').classList.add('hidden'); }
        }

        function closeStatsModal() { document.getElementById('statsModal').classList.add('hidden'); }

        function renderChart(dailyData) {
            const ctx = document.getElementById('clicksChart').getContext('2d');
            if (chartInstance) chartInstance.destroy();
            
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(0, 176, 164, 0.5)');
            gradient.addColorStop(1, 'rgba(0, 176, 164, 0)');

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dailyData.map(d => d.date),
                    datasets: [{
                        label: 'بازدید',
                        data: dailyData.map(d => d.count),
                        borderColor: '#00B0A4',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        pointBackgroundColor: '#010101',
                        pointBorderColor: '#00B0A4',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0D0D0D',
                            titleColor: '#fff',
                            bodyColor: '#00B0A4',
                            borderColor: '#1F1F1F',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            titleFont: { family: 'Vazirmatn' },
                            bodyFont: { family: 'Vazirmatn' }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#1F1F1F', drawBorder: false },
                            ticks: { color: '#9CA3AF', font: { family: 'Vazirmatn' } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#9CA3AF', font: { family: 'Vazirmatn' } }
                        }
                    }
                }
            });
        }

        function renderBrowsers(browsers) {
            const container = document.getElementById('browserStatsList');
            container.innerHTML = '';
            
            // رنگ‌های مختلف برای مرورگرها
            const colors = ['border-primary', 'border-blue-500', 'border-yellow-500', 'border-purple-500', 'border-red-500'];
            let i = 0;

            for (const [name, count] of Object.entries(browsers)) {
                const colorClass = colors[i % colors.length];
                container.innerHTML += `
                    <div class="bg-card border-r-4 ${colorClass} border-y border-l border-border p-4 rounded-xl flex items-center justify-between hover:bg-white/5 transition-colors">
                        <span class="font-bold text-sm text-white">${name}</span>
                        <span class="bg-white/5 text-white font-mono text-sm px-3 py-1 rounded-lg border border-white/10">${count}</span>
                    </div>
                `;
                i++;
            }
            if (Object.keys(browsers).length === 0) {
                container.innerHTML = '<p class="text-sec text-sm col-span-full text-center py-4">هنوز داده‌ای ثبت نشده است.</p>';
            }
        }

        // Close modal on outside click
        document.getElementById('statsModal').addEventListener('click', e => { if(e.target === this) closeStatsModal(); });
    </script>
</body>
</html>