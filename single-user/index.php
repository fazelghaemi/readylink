<?php
/**
 * RDYS - Professional URL Shortener
 * Frontend Interface
 * * @author Senior Developer
 * @version 2.0.0
 */

// اتصال به هسته سیستم
// اگر فایل functions هنوز ساخته نشده، خطا را مدیریت می‌کنیم تا صفحه سفید نشود
if (file_exists('functions.php')) {
    require_once 'functions.php';
} else {
    // موقت برای نمایش UI در صورتی که توابع هنوز نیستند (جهت تست ظاهری)
    $domains = [['id' => 1, 'domain' => $_SERVER['HTTP_HOST'] ?? 'rdys.ir']];
    function isAdmin() { return false; }
}

// دریافت لیست دامنه‌های فعال برای دراپ‌دان
try {
    global $pdo;
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM domains WHERE is_active = 1 ORDER BY id ASC");
        $stmt->execute();
        $domains = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // در صورت خطای دیتابیس، فقط دامنه پیش‌فرض نمایش داده شود
    $domains = [['id' => 1, 'domain' => $_SERVER['HTTP_HOST']]];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="سرویس کوتاه‌کننده لینک حرفه‌ای و امن">
    <meta name="theme-color" content="#15202B">
    
    <title>RDYS | کوتاه‌کننده لینک مدرن</title>
    
    <!-- Fonts: Vazirmatn -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    
    <!-- Tailwind CSS (CDN for simplicity, logical in single-file architecture) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Vazirmatn', 'sans-serif'],
                        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', "Liberation Mono", "Courier New", "monospace"],
                    },
                    colors: {
                        // Twitter Dim Palette
                        bg: "#15202B",      // Main Background
                        card: "#192734",    // Card Background
                        primary: "#00B0A4", // Primary Blue
                        text: "#F7F9F9",    // White Text
                        sec: "#8899A6",     // Secondary Text (Grey)
                        border: "#38444D",  // Borders
                        hover: "#1c2732",   // Hover State
                        
                        // Functional Colors
                        success: "#00BA7C",
                        error: "#F91880",
                    },
                    boxShadow: {
                        'glow': '0 0 20px -5px rgba(29, 155, 240, 0.3)',
                        'material': '0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2)',
                    },
                    animation: {
                        'bounce-slight': 'bounce-slight 2s infinite',
                        'fade-in': 'fadeIn 0.5s ease-out forwards',
                    },
                    keyframes: {
                        'bounce-slight': {
                            '0%, 100%': { transform: 'translateY(-2%)' },
                            '50%': { transform: 'translateY(0)' },
                        },
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #15202B; }
        ::-webkit-scrollbar-thumb { background: #38444D; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #00B0A4; }
        
        /* Glass Effect Helpers */
        .glass-panel {
            background: rgba(25, 39, 52, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        
        /* Remove tap highlight on mobile */
        * { -webkit-tap-highlight-color: transparent; }
    </style>
</head>
<body class="bg-bg text-text min-h-screen flex flex-col antialiased selection:bg-primary selection:text-white overflow-x-hidden">

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 w-full border-b border-border glass-panel">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center gap-2 cursor-default select-none">
                    <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center shadow-glow">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                    </div>
                    <span class="text-xl font-black tracking-tight text-text">rdys<span class="text-primary">.ir</span></span>
                </div>

                <!-- Auth Button -->
                <div>
                    <?php if(function_exists('isAdmin') && isAdmin()): ?>
                        <a href="admin.php" class="flex items-center gap-2 bg-card hover:bg-border border border-border text-text px-4 py-2 rounded-full transition-all duration-200 text-sm font-medium group">
                            <span>داشبورد</span>
                            <svg class="w-4 h-4 text-sec group-hover:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="text-sec hover:text-text font-medium text-sm transition-colors duration-200 px-3 py-2">ورود مدیر</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow flex flex-col items-center justify-center p-4 relative w-full max-w-7xl mx-auto">
        
        <!-- Background Decorations (Subtle Blobs) -->
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-primary/10 rounded-full blur-[128px] pointer-events-none"></div>
        <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-error/5 rounded-full blur-[128px] pointer-events-none"></div>

        <div class="w-full max-w-3xl z-10 text-center animate-fade-in">
            
            <!-- Hero Text -->
            <h1 class="text-4xl md:text-6xl font-extrabold mb-6 leading-tight">
                لینک‌های خود را <br class="md:hidden" />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-blue-400">هوشمندانه کوتاه</span> کنید
            </h1>
            <p class="text-sec text-lg mb-10 max-w-2xl mx-auto leading-relaxed">
                ابزاری ساده، سریع و امن برای مدیریت لینک‌های طولانی شما. بدون تبلیغات مزاحم و با زیرساخت قدرتمند.
            </p>

            <!-- Shortener Card -->
            <div class="bg-card border border-border rounded-3xl p-2 md:p-3 shadow-material hover:shadow-glow transition-shadow duration-500 relative group">
                <form id="shortenForm" class="flex flex-col md:flex-row items-stretch gap-2" novalidate>
                    
                    <!-- Domain Selector -->
                    <div class="relative min-w-[140px]">
                        <select name="domain_id" class="w-full h-full bg-bg/50 md:bg-transparent text-text border border-border md:border-none rounded-xl md:rounded-l-2xl px-4 py-3 md:py-0 appearance-none outline-none focus:ring-0 cursor-pointer font-bold text-sm transition-colors hover:text-primary ltr text-center md:text-left">
                            <?php foreach($domains as $domain): ?>
                                <option value="<?php echo htmlspecialchars($domain['id']); ?>" class="bg-card text-text">
                                    <?php echo htmlspecialchars($domain['domain']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Custom Arrow -->
                        <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-sec">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                        <!-- Divider for Desktop -->
                        <div class="hidden md:block absolute right-0 top-1/2 -translate-y-1/2 h-8 w-[1px] bg-border"></div>
                    </div>

                    <!-- URL Input -->
                    <input type="url" name="url" id="urlInput" placeholder="لینک طولانی را اینجا وارد کنید (https://...)" 
                           class="flex-grow bg-transparent text-text placeholder-sec/60 px-4 py-3 md:py-4 text-base md:text-lg outline-none border-none focus:ring-0 w-full dir-ltr font-mono" required autocomplete="off">

                    <!-- Submit Button -->
                    <button type="submit" id="submitBtn" class="bg-primary hover:bg-blue-500 text-white font-bold rounded-xl md:rounded-2xl px-8 py-3 md:py-0 transition-all active:scale-95 flex items-center justify-center gap-2 min-w-[120px]">
                        <span class="whitespace-nowrap">کوتاه کن</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <!-- Loader (Hidden by default) -->
                        <div id="btnLoader" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                    </button>
                </form>
            </div>

            <!-- Advanced Options Toggle -->
            <div class="mt-4 flex flex-col items-center">
                <button type="button" onclick="document.getElementById('advancedOptions').classList.toggle('hidden'); this.classList.toggle('text-primary')" 
                        class="text-sec text-sm hover:text-text flex items-center gap-1 transition-colors py-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <span>تنظیمات پیشرفته</span>
                </button>
                
                <div id="advancedOptions" class="hidden w-full max-w-sm mt-3 animate-fade-in">
                    <div class="relative">
                        <input type="text" id="customAlias" placeholder="نام مستعار دلخواه (مثلاً: my-offer)" 
                               class="w-full bg-bg border border-border rounded-xl px-4 py-2 text-text text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none text-center ltr placeholder-sec/50 transition-all">
                        <div class="absolute right-3 top-2.5 text-xs text-sec bg-bg px-1">اختیاری</div>
                    </div>
                </div>
            </div>

            <!-- Result Section -->
            <div id="resultContainer" class="hidden mt-8 w-full animate-fade-in">
                <div class="bg-card border border-success/30 rounded-2xl p-6 shadow-2xl relative overflow-hidden">
                    <!-- Success Glow Background -->
                    <div class="absolute top-0 right-0 w-full h-1 bg-gradient-to-r from-transparent via-success to-transparent opacity-50"></div>
                    
                    <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                        
                        <!-- Text Info -->
                        <div class="flex-grow text-center md:text-right overflow-hidden w-full">
                            <p class="text-sec text-xs mb-2 uppercase tracking-wider font-bold">لینک کوتاه شما آماده است</p>
                            <div class="flex items-center justify-center md:justify-start gap-3 bg-bg/50 p-3 rounded-xl border border-border group hover:border-success/50 transition-colors">
                                <a id="shortUrlDisplay" href="#" target="_blank" class="text-2xl font-mono text-success hover:underline truncate dir-ltr select-all"></a>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-3 shrink-0">
                            <!-- Copy Button -->
                            <button onclick="copyToClipboard()" class="flex flex-col items-center justify-center w-16 h-16 bg-bg border border-border hover:border-primary hover:text-primary rounded-xl transition-all group" title="کپی لینک">
                                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                <span class="text-[10px] font-bold">کپی</span>
                            </button>
                            
                            <!-- QR Code Button (Triggers Modal or View) -->
                            <a id="qrDownloadLink" href="#" target="_blank" class="flex flex-col items-center justify-center w-16 h-16 bg-bg border border-border hover:border-white hover:text-white rounded-xl transition-all" title="دانلود QR">
                                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4h2v-4zM6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span class="text-[10px] font-bold">QR</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <div id="errorContainer" class="hidden mt-6 bg-error/10 border border-error/20 text-error px-4 py-3 rounded-xl text-sm font-bold animate-fade-in flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span id="errorText">خطایی رخ داد</span>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full border-t border-border mt-auto glass-panel">
        <div class="max-w-7xl mx-auto px-4 py-6 flex flex-col md:flex-row items-center justify-between text-sec text-sm">
            <p>&copy; <?php echo date('Y'); ?> <span class="text-text font-bold">RDYS</span>. تمامی حقوق برای ردی استودیو محفوظ است.</p>
            <div class="flex gap-4 mt-2 md:mt-0">
                <a href="https://readystudio.ir/about-us/" class="hover:text-primary transition-colors">درباره ما</a>
                <a href="https://readystudio.ir/contact-us" class="hover:text-primary transition-colors">تماس با ما</a>
            </div>
        </div>
    </footer>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-text text-bg px-6 py-3 rounded-full shadow-2xl translate-y-20 opacity-0 transition-all duration-300 font-bold flex items-center gap-2 z-[60]">
        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        لینک کپی شد!
    </div>

    <!-- JavaScript Logic -->
    <script>
        const form = document.getElementById('shortenForm');
        const urlInput = document.getElementById('urlInput');
        const customAlias = document.getElementById('customAlias');
        const submitBtn = document.getElementById('submitBtn');
        const btnLoader = document.getElementById('btnLoader');
        const btnText = submitBtn.querySelector('span'); // متن دکمه
        const btnIcon = submitBtn.querySelector('svg'); // آیکون دکمه
        
        const resultContainer = document.getElementById('resultContainer');
        const errorContainer = document.getElementById('errorContainer');
        const errorText = document.getElementById('errorText');
        
        const shortUrlDisplay = document.getElementById('shortUrlDisplay');
        const qrDownloadLink = document.getElementById('qrDownloadLink');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Basic Validation
            if (!urlInput.value.trim()) {
                showError('لطفا آدرس لینک را وارد کنید.');
                urlInput.focus();
                return;
            }

            // UI Loading State
            setLoading(true);
            hideError();
            resultContainer.classList.add('hidden');

            try {
                const formData = new FormData(form);
                formData.append('action', 'shorten');
                if(customAlias.value.trim()) formData.append('alias', customAlias.value.trim());

                // ارسال به API (که در مرحله بعد می‌سازیم)
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });

                // اگر API هنوز وجود ندارد یا خطا داد (برای تست اولیه)
                if (response.status === 404) {
                    throw new Error('فایل api.php یافت نشد. لطفا فایل‌های بعدی را آپلود کنید.');
                }

                const data = await response.json();

                if (data.success) {
                    showResult(data.url);
                    urlInput.value = ''; // Clear input
                    customAlias.value = '';
                } else {
                    showError(data.message || 'خطای ناشناخته در سرور');
                }

            } catch (error) {
                console.error(error);
                showError('خطا در ارتباط با سرور. لطفا اتصال اینترنت را بررسی کنید.');
            } finally {
                setLoading(false);
            }
        });

        function showResult(url) {
            // Remove protocols for cleaner display
            const cleanUrl = url.replace(/(^\w+:|^)\/\//, '');
            
            shortUrlDisplay.href = url;
            shortUrlDisplay.textContent = cleanUrl;
            
            // Generate QR Code URL (using generic API for now)
            qrDownloadLink.href = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(url)}`;
            
            resultContainer.classList.remove('hidden');
            resultContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function showError(msg) {
            errorText.textContent = msg;
            errorContainer.classList.remove('hidden');
        }

        function hideError() {
            errorContainer.classList.add('hidden');
        }

        function setLoading(isLoading) {
            if (isLoading) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                btnLoader.classList.remove('hidden');
                btnText.textContent = 'در حال ساخت...';
                btnIcon.classList.add('hidden');
            } else {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                btnLoader.classList.add('hidden');
                btnText.textContent = 'کوتاه کن';
                btnIcon.classList.remove('hidden');
            }
        }

        function copyToClipboard() {
            const url = shortUrlDisplay.href;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(showToast);
            } else {
                // Fallback for older browsers
                const textArea = document.createElement("textarea");
                textArea.value = url;
                textArea.style.position = "fixed";
                textArea.style.left = "-9999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    showToast();
                } catch (err) {
                    console.error('Copy failed', err);
                }
                document.body.removeChild(textArea);
            }
        }

        function showToast() {
            const toast = document.getElementById('toast');
            toast.classList.remove('translate-y-20', 'opacity-0');
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }
    </script>
</body>
</html>