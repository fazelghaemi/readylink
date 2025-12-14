<?php
/**
 * RDYS - User & Admin Dashboard (Ultimate Edition - Fixed Shortener)
 * Theme: Ready Studio (Teal & Black)
 * Version: 11.0.1
 * * Fixes: Added missing 'action' parameter to shorten link AJAX request.
 */

require_once 'functions.php';

// 1. امنیت و دسترسی
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$isSuperAdmin = ($currentUser['role'] === 'admin');

// 2. دریافت آمار و اطلاعات
try {
    // شرط SQL بر اساس نقش کاربر
    $userCondition = $isSuperAdmin ? "1" : "user_id = $userId";
    
    // آمار کلی
    $totalLinks = $pdo->query("SELECT COUNT(*) FROM links WHERE $userCondition")->fetchColumn();
    
    if ($isSuperAdmin) {
        $totalViews = $pdo->query("SELECT COUNT(*) FROM stats")->fetchColumn();
    } else {
        $totalViews = $pdo->query("SELECT SUM(views) FROM links WHERE user_id = $userId")->fetchColumn() ?: 0;
    }
    
    // لیست لینک‌ها
    $userJoin = $isSuperAdmin ? "LEFT JOIN users u ON l.user_id = u.id" : "";
    $userSelect = $isSuperAdmin ? ", u.username as creator" : "";
    
    $stmt = $pdo->prepare("
        SELECT 
            l.id, l.long_url, l.short_code, l.created_at, l.views,
            d.domain
            $userSelect
        FROM links l
        LEFT JOIN domains d ON l.domain_id = d.id
        $userJoin
        WHERE " . ($isSuperAdmin ? "1" : "l.user_id = ?") . "
        ORDER BY l.created_at DESC 
        LIMIT 50
    ");
    
    if ($isSuperAdmin) $stmt->execute(); else $stmt->execute([$userId]);
    $recentLinks = $stmt->fetchAll();

    // لیست دامنه‌ها
    if ($isSuperAdmin) {
        $stmt = $pdo->query("SELECT * FROM domains ORDER BY id DESC");
        $domainsList = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM domains WHERE user_id = ? OR (user_id = 1 AND is_active = 1) ORDER BY id DESC");
        $stmt->execute([$userId]);
        $domainsList = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    die("خطا در بارگذاری اطلاعات: " . $e->getMessage());
}

$linkLimit = $currentUser['link_limit'];
$linkUsagePercent = ($linkLimit > 0) ? min(100, round(($totalLinks / $linkLimit) * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>داشبورد | RDYS</title>
    
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 
                        bg: "#010101", card: "#0D0D0D", primary: "#00B0A4", text: "#FFFFFF", 
                        sec: "#9CA3AF", border: "#1F1F1F", success: "#00B0A4", error: "#FF3B30" 
                    },
                    fontFamily: { sans: ['ReadyFont', 'Vazirmatn', 'sans-serif'], mono: ['ui-monospace', 'monospace'] }
                }
            }
        }
    </script>
    <style>
        .nav-btn {
            position: relative;
            transition: all 0.3s ease;
            border-right: 3px solid transparent;
        }
        .nav-btn:hover { background-color: rgba(255, 255, 255, 0.05); color: #fff; }
        .nav-btn.active {
            background: linear-gradient(90deg, rgba(0, 176, 164, 0.1) 0%, transparent 100%);
            border-right-color: var(--primary);
            color: var(--primary);
        }
        .nav-btn.active svg { color: var(--primary); }
        
        .admin-input {
            background-color: #010101;
            border: 1px solid var(--border-color);
            color: white;
            transition: all 0.2s;
        }
        .admin-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(0, 176, 164, 0.2);
        }
    </style>
</head>
<body class="h-screen flex overflow-hidden bg-bg text-text font-sans selection:bg-primary selection:text-white">
    
    <!-- Sidebar -->
    <aside class="hidden md:flex flex-col w-72 bg-card border-l border-border h-full flex-shrink-0 z-30 shadow-2xl">
        <div class="p-6 flex items-center gap-4 border-b border-border/50">
            <div class="w-12 h-12 rounded-full flex items-center justify-center bg-bg border border-border text-primary font-black text-xl shadow-inner">
                <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
            </div>
            <div class="overflow-hidden">
                <h1 class="font-bold text-white truncate text-sm" title="<?php echo htmlspecialchars($currentUser['username']); ?>">
                    <?php echo htmlspecialchars($currentUser['username']); ?>
                </h1>
                <div class="flex items-center gap-1 mt-1">
                    <span class="text-[10px] text-sec uppercase tracking-widest font-bold">پلن:</span>
                    <span class="text-[10px] text-bg bg-primary font-bold px-1.5 py-0.5 rounded-md">
                        <?php echo htmlspecialchars($currentUser['plan_name']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <nav class="flex-grow p-4 space-y-2 overflow-y-auto custom-scrollbar">
            <p class="px-4 text-xs font-bold text-sec/50 uppercase tracking-wider mb-2 mt-2">منوی اصلی</p>
            
            <button onclick="switchTab('dashboard')" id="nav-dashboard" class="nav-btn active w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sec text-sm font-bold cursor-pointer">
                <svg class="w-5 h-5 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 16a2 2 0 012-2h2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span>داشبورد و ساخت لینک</span>
            </button>
            
            <button onclick="switchTab('domains')" id="nav-domains" class="nav-btn w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sec text-sm font-bold cursor-pointer">
                <svg class="w-5 h-5 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                <span>مدیریت دامنه‌ها</span>
            </button>

            <button onclick="switchTab('api')" id="nav-api" class="nav-btn w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sec text-sm font-bold cursor-pointer">
                <svg class="w-5 h-5 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                <span>تنظیمات API</span>
            </button>

            <div class="mt-8 px-4">
                <div class="flex justify-between text-xs text-sec mb-1">
                    <span>مصرف لینک</span>
                    <span><?php echo $totalLinks . ' / ' . $linkLimit; ?></span>
                </div>
                <div class="w-full bg-border rounded-full h-1.5 overflow-hidden">
                    <div class="bg-primary h-1.5 rounded-full transition-all duration-1000" style="width: <?php echo $linkUsagePercent; ?>%"></div>
                </div>
            </div>
        </nav>
        
        <div class="p-4 border-t border-border/50 bg-bg/50">
            <a href="?logout=true" class="flex items-center justify-center gap-2 w-full bg-error/10 hover:bg-error/20 text-error border border-error/20 font-bold py-2.5 rounded-xl transition-all text-sm group">
                <svg class="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                خروج
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow h-full overflow-y-auto relative w-full bg-bg custom-scrollbar">
        
        <!-- Mobile Header -->
        <header class="md:hidden flex items-center justify-between p-4 bg-card/80 backdrop-blur-md border-b border-border sticky top-0 z-30">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center shadow-glow font-bold text-white text-xs">R</div>
                <h1 class="font-black text-lg text-white">RDYS</h1>
            </div>
            <a href="?logout=true" class="p-2 text-error hover:bg-error/10 bg-bg rounded-lg border border-border">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </a>
        </header>

        <!-- Mobile Tabs -->
        <div class="md:hidden flex border-b border-border bg-card sticky top-[65px] z-20 overflow-x-auto no-scrollbar">
            <button onclick="switchTab('dashboard')" class="flex-1 py-4 px-2 text-sm font-bold text-center border-b-2 border-primary text-primary whitespace-nowrap" id="mob-dashboard">داشبورد</button>
            <button onclick="switchTab('domains')" class="flex-1 py-4 px-2 text-sm font-bold text-center border-b-2 border-transparent text-sec hover:text-white whitespace-nowrap" id="mob-domains">دامنه‌ها</button>
            <button onclick="switchTab('api')" class="flex-1 py-4 px-2 text-sm font-bold text-center border-b-2 border-transparent text-sec hover:text-white whitespace-nowrap" id="mob-api">API</button>
        </div>

        <div class="p-6 md:p-10 max-w-7xl mx-auto pb-24">
            
            <!-- DASHBOARD TAB -->
            <div id="tab-dashboard" class="animate-fade-in space-y-8">
                
                <!-- Shortener Form -->
                <div class="rdys-card p-6 rounded-2xl border-primary/30 relative overflow-hidden shadow-[0_0_50px_-20px_rgba(0,176,164,0.15)]">
                    <div class="absolute top-0 left-0 w-1 h-full bg-primary"></div>
                    <h2 class="text-xl font-black text-white mb-6 flex items-center gap-2">
                        <div class="p-2 bg-primary/10 rounded-lg text-primary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg></div>
                        ساخت لینک جدید
                    </h2>
                    
                    <form id="adminShortenForm" class="flex flex-col lg:flex-row gap-4 items-start">
                        <div class="w-full lg:w-56 relative group">
                            <label class="block text-sec text-[10px] font-bold uppercase mb-1">دامنه</label>
                            <div class="relative">
                                <select name="domain_id" class="admin-input w-full rounded-xl px-4 py-3 pl-10 appearance-none outline-none cursor-pointer font-bold text-sm ltr text-right lg:text-left">
                                    <?php foreach($domainsList as $domain): 
                                        if(isset($domain['is_active']) && !$domain['is_active']) continue;
                                    ?>
                                        <option value="<?php echo htmlspecialchars($domain['id']); ?>"><?php echo htmlspecialchars($domain['domain']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-primary"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></div>
                            </div>
                        </div>
                        <div class="flex-grow w-full">
                            <label class="block text-sec text-[10px] font-bold uppercase mb-1">لینک مقصد</label>
                            <input type="url" name="url" placeholder="https://..." required class="admin-input w-full rounded-xl px-4 py-3 outline-none ltr placeholder-sec/30 text-sm font-medium">
                        </div>
                        <div class="w-full lg:w-48">
                            <label class="block text-sec text-[10px] font-bold uppercase mb-1">نام مستعار</label>
                            <input type="text" name="alias" placeholder="my-link" class="admin-input w-full rounded-xl px-4 py-3 outline-none ltr text-center text-sm placeholder-sec/30 font-medium">
                        </div>
                        <div class="w-full lg:w-auto pt-5">
                            <button type="submit" class="w-full lg:w-auto bg-primary hover:bg-[#008F85] text-white font-bold px-8 py-3 rounded-xl transition-all shadow-glow flex items-center justify-center gap-2 transform active:scale-95">
                                <span>کوتاه کن</span>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="rdys-card p-6 rounded-2xl group"><p class="text-sec text-xs font-bold uppercase tracking-widest mb-2">لینک‌های من</p><p class="text-4xl font-black text-white group-hover:text-primary transition-colors"><?php echo number_format($totalLinks); ?></p></div>
                    <div class="rdys-card p-6 rounded-2xl group"><p class="text-sec text-xs font-bold uppercase tracking-widest mb-2">مجموع بازدیدها</p><p class="text-4xl font-black text-primary"><?php echo number_format($totalViews); ?></p></div>
                    <div class="rdys-card p-6 rounded-2xl group"><p class="text-sec text-xs font-bold uppercase tracking-widest mb-2">وضعیت پلن</p><p class="text-xl font-bold text-white mb-1"><?php echo htmlspecialchars($currentUser['plan_name']); ?></p><div class="w-full bg-border rounded-full h-1.5 mt-2 overflow-hidden"><div class="bg-success h-1.5 rounded-full" style="width: <?php echo $linkUsagePercent; ?>%"></div></div></div>
                </div>

                <!-- Recent Links -->
                <div class="bg-card border border-border rounded-3xl overflow-hidden shadow-2xl relative">
                    <div class="p-6 border-b border-border bg-card/95 backdrop-blur-xl sticky top-0 z-10">
                        <h2 class="font-bold text-lg text-white">آخرین لینک‌های شما</h2>
                    </div>
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-right text-sm">
                            <thead class="bg-bg text-sec border-b border-border/50 uppercase text-xs tracking-wider">
                                <tr>
                                    <th class="p-5 font-bold">لینک کوتاه</th>
                                    <th class="p-5 font-bold">لینک مقصد</th>
                                    <?php if($isSuperAdmin): ?><th class="p-5 font-bold">کاربر</th><?php endif; ?>
                                    <th class="p-5 font-bold text-center">کلیک</th>
                                    <th class="p-5 font-bold text-center">زمان</th>
                                    <th class="p-5 font-bold text-center">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/50 text-white">
                                <?php if (count($recentLinks) > 0): foreach ($recentLinks as $link): ?>
                                    <tr class="hover:bg-white/5 transition-colors group">
                                        <td class="p-5 ltr text-left">
                                            <div class="flex flex-col">
                                                <a href="<?php echo 'http://' . $link['domain'] . '/' . $link['short_code']; ?>" target="_blank" class="text-primary font-mono font-bold hover:text-white transition-colors text-base">/<?php echo htmlspecialchars($link['short_code']); ?></a>
                                                <span class="text-[10px] text-sec mt-1 font-mono opacity-60"><?php echo htmlspecialchars($link['domain']); ?></span>
                                            </div>
                                        </td>
                                        <td class="p-5 max-w-xs" title="<?php echo htmlspecialchars($link['long_url']); ?>"><div class="truncate dir-ltr font-mono text-sec group-hover:text-white transition-colors bg-bg/50 px-3 py-1.5 rounded-lg border border-transparent group-hover:border-border"><?php echo htmlspecialchars($link['long_url']); ?></div></td>
                                        <?php if($isSuperAdmin): ?><td class="p-5 text-xs text-sec font-mono"><?php echo htmlspecialchars($link['creator'] ?? 'Unknown'); ?></td><?php endif; ?>
                                        <td class="p-5 text-center"><span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-primary/10 text-primary border border-primary/20"><?php echo number_format($link['views']); ?></span></td>
                                        <td class="p-5 text-center text-sec text-xs font-bold whitespace-nowrap"><?php echo timeAgo($link['created_at']); ?></td>
                                        <td class="p-5 text-center whitespace-nowrap">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick="openStatsModal(<?php echo $link['id']; ?>)" class="p-2 text-primary bg-primary/5 hover:bg-primary hover:text-bg rounded-lg border border-primary/20 transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg></button>
                                                <button onclick="deleteLink(<?php echo $link['id']; ?>, this)" class="p-2 text-error bg-error/5 hover:bg-error hover:text-white rounded-lg border border-error/20 transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="6" class="p-12 text-center text-sec">هنوز لینکی نساخته‌اید.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- DOMAINS TAB -->
            <div id="tab-domains" class="hidden animate-fade-in">
                <div class="bg-card rounded-3xl border border-border p-8 max-w-4xl mx-auto shadow-2xl relative overflow-hidden">
                    <div class="flex items-center gap-3 mb-8 border-b border-border pb-6 relative z-10">
                        <div class="p-3 bg-primary/10 rounded-xl text-primary border border-primary/20"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg></div>
                        <div><h2 class="text-2xl font-black text-white">مدیریت دامنه‌ها</h2><p class="text-sec text-sm">اتصال دامنه‌های شخصی به سیستم</p></div>
                    </div>
                    
                    <form onsubmit="addDomain(event)" class="flex flex-col md:flex-row gap-4 mb-10 bg-bg p-6 rounded-2xl border border-border shadow-inner relative z-10">
                        <div class="flex-grow relative group">
                            <label class="block text-sec text-[10px] font-bold uppercase mb-1 mr-1">آدرس دامنه</label>
                            <div class="relative"><div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-sec group-focus-within:text-primary transition-colors"><span class="font-bold text-sm">https://</span></div><input type="text" name="domain" placeholder="example.com" required class="admin-input w-full rounded-xl px-4 py-3 pr-20 outline-none ltr placeholder-sec/30 font-mono"></div>
                        </div>
                        <div class="pt-5"><button type="submit" class="bg-primary hover:bg-[#008F85] text-white font-black py-3 px-8 rounded-xl transition-all shadow-glow transform active:scale-95 whitespace-nowrap h-[48px]">افزودن دامنه</button></div>
                    </form>

                    <div class="space-y-4 relative z-10">
                        <?php foreach ($domainsList as $domain): 
                            $isOwner = ($isSuperAdmin || $domain['user_id'] == $userId);
                            $isActive = isset($domain['is_active']) ? (bool)$domain['is_active'] : false;
                        ?>
                        <div class="flex items-center justify-between p-5 bg-bg/50 backdrop-blur-sm rounded-2xl border border-border hover:border-primary/40 transition-all group">
                            <div class="flex items-center gap-4">
                                <div class="w-3 h-3 rounded-full <?php echo $isActive ? 'bg-success shadow-[0_0_10px_#00B0A4]' : 'bg-error'; ?>"></div>
                                <span class="font-mono text-xl font-bold ltr text-white group-hover:text-primary transition-colors"><?php echo htmlspecialchars($domain['domain']); ?></span>
                                <?php if(!$isOwner): ?><span class="text-[10px] bg-border px-2 py-0.5 rounded text-sec border border-white/10">عمومی</span><?php endif; ?>
                            </div>
                            <?php if($isOwner): ?>
                            <button onclick="toggleDomain(<?php echo $domain['id']; ?>)" class="<?php echo $isActive ? 'text-primary border-primary/30 bg-primary/5 hover:bg-error hover:text-white hover:border-error' : 'text-sec border-border bg-card hover:text-success hover:border-success'; ?> text-xs font-bold border px-6 py-2.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer shadow-sm">
                                <span><?php echo $isActive ? 'فعال' : 'غیرفعال'; ?></span>
                            </button>
                            <?php else: ?><span class="text-xs text-sec opacity-50">فقط خواندنی</span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- API TAB -->
            <div id="tab-api" class="hidden animate-fade-in">
                <div class="bg-card rounded-3xl border border-border p-8 max-w-4xl mx-auto shadow-2xl">
                    <div class="flex items-center gap-3 mb-8 border-b border-border pb-6"><div class="p-3 bg-primary/10 rounded-xl text-primary border border-primary/20"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg></div><div><h2 class="text-2xl font-black text-white">تنظیمات API</h2><p class="text-sec text-sm">کلید دسترسی برای توسعه‌دهندگان</p></div></div>
                    <?php if ($currentUser['has_api']): ?>
                        <div class="bg-bg border border-border rounded-2xl p-6 relative overflow-hidden"><p class="text-xs text-primary font-bold mb-4 uppercase tracking-widest flex items-center gap-2"><span class="w-2 h-2 bg-primary rounded-full animate-pulse"></span> Bearer Token فعال</p><div class="flex flex-col md:flex-row gap-4"><code id="apiTokenDisplay" class="flex-grow bg-card border border-border text-white font-mono p-4 rounded-xl break-all select-all text-sm shadow-inner cursor-pointer" onclick="document.execCommand('copy')" title="کپی"><?php echo htmlspecialchars($currentUser['api_token']); ?></code><button onclick="regenerateToken()" class="bg-card hover:bg-error hover:text-white text-sec border border-border hover:border-error px-6 py-4 rounded-xl transition-all whitespace-nowrap font-bold flex items-center justify-center gap-2 group"><svg class="w-4 h-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> تغییر توکن</button></div></div>
                    <?php else: ?><div class="bg-error/5 border border-error/20 p-8 rounded-2xl text-center"><p class="text-error font-bold mb-2">دسترسی محدود</p><p class="text-sec text-sm">پلن شما دسترسی API ندارد.</p></div><?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Stats Modal -->
    <div id="statsModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/90 backdrop-blur-md p-4 animate-fade-in"><div class="bg-card w-full max-w-5xl rounded-3xl border border-border shadow-2xl overflow-hidden relative flex flex-col max-h-[90vh]"><div class="p-6 border-b border-border flex justify-between items-center bg-bg"><h3 class="text-xl font-black text-white">آمار بازدید</h3><button onclick="closeStatsModal()" class="text-sec hover:text-error transition"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button></div><div class="p-8 overflow-y-auto flex-grow custom-scrollbar"><div class="h-[350px] w-full mb-10 relative bg-bg p-4 rounded-2xl border border-border"><canvas id="clicksChart"></canvas></div><div id="browserStatsList" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div></div><div id="chartLoader" class="absolute inset-0 bg-bg/95 flex flex-col items-center justify-center z-10 hidden"><div class="w-12 h-12 border-4 border-border border-t-primary rounded-full animate-spin mb-4"></div></div></div></div>

    <script>
        function switchTab(t) {
            ['dashboard', 'domains', 'api'].forEach(tab => { document.getElementById('tab-'+tab).classList.add('hidden'); document.getElementById('nav-'+tab).classList.remove('active'); const mob=document.getElementById('mob-'+tab); if(mob){mob.classList.remove('border-primary','text-primary');mob.classList.add('border-transparent','text-sec');} });
            document.getElementById('tab-'+t).classList.remove('hidden'); document.getElementById('nav-'+t).classList.add('active'); const activeMob=document.getElementById('mob-'+t); if(activeMob){activeMob.classList.add('border-primary','text-primary');activeMob.classList.remove('border-transparent','text-sec');}
        }
        
        document.getElementById('adminShortenForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-spin w-5 h-5 border-2 border-white/30 border-t-white rounded-full"></span>';
            
            // FIX: Append action manually
            const formData = new FormData(e.target);
            formData.append('action', 'shorten');
            
            try {
                const res = await fetch('api.php', { method: 'POST', body: formData });
                const d = await res.json();
                if(d.success) location.reload(); else alert(d.message);
            } catch(er) { alert('خطا'); } finally { btn.disabled = false; btn.innerHTML = original; }
        });

        async function deleteLink(id, btn) { if(confirm('حذف شود؟')) { const fd=new FormData(); fd.append('action','delete'); fd.append('id',id); fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success) btn.closest('tr').remove(); else alert(d.message); }); } }
        async function addDomain(e) { e.preventDefault(); const fd=new FormData(e.target); fd.append('action','add_domain'); fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else alert(d.message); }); }
        async function toggleDomain(id) { const fd=new FormData(); fd.append('action','toggle_domain'); fd.append('id',id); fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else alert(d.message); }); }
        async function regenerateToken() { if(confirm('مطمئنید؟')) { const fd=new FormData(); fd.append('action','regenerate_token'); fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success) location.reload(); }); } }
        let chartInstance = null;
        async function openStatsModal(id) { document.getElementById('statsModal').classList.remove('hidden'); document.getElementById('chartLoader').classList.remove('hidden'); const fd=new FormData(); fd.append('action','get_stats'); fd.append('id',id); const d = await (await fetch('api.php', {method:'POST', body:fd})).json(); document.getElementById('chartLoader').classList.add('hidden'); if(d.success) { const ctx = document.getElementById('clicksChart').getContext('2d'); if(chartInstance) chartInstance.destroy(); chartInstance = new Chart(ctx, { type:'line', data:{labels:d.daily.map(x=>x.date), datasets:[{label:'بازدید',data:d.daily.map(x=>x.count),borderColor:'#00B0A4',backgroundColor:'rgba(0,176,164,0.2)',fill:true,tension:0.4,pointBackgroundColor:'#010101',pointBorderColor:'#00B0A4',pointBorderWidth:2}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{color:'#9CA3AF',font:{family:'Vazirmatn'}}},y:{grid:{color:'#1F1F1F'},ticks:{color:'#9CA3AF',font:{family:'Vazirmatn'}}}}} }); document.getElementById('browserStatsList').innerHTML = Object.entries(d.browsers).map(([k,v])=>`<div class="bg-bg border border-border p-4 rounded-xl flex justify-between items-center hover:bg-white/5 transition-colors"><span class="text-sec text-sm font-bold">${k}</span><span class="text-white font-mono font-bold bg-card px-2 py-1 rounded border border-border">${v}</span></div>`).join(''); } }
        function closeStatsModal() { document.getElementById('statsModal').classList.add('hidden'); }
        document.getElementById('statsModal').addEventListener('click', function(e) { if(e.target === this) closeStatsModal(); });
    </script>
</body>
</html>