<?php
/**
 * RDYS - Custom 404 Error Page (Ready Studio Edition)
 * Theme: 3D Dark Mode
 * Version: 6.0.0
 * * Features: 3D Hero Asset, Glassmorphism, Floating Animations.
 */

if (file_exists('config.php')) {
    include_once 'config.php';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="صفحه مورد نظر یافت نشد">
    <meta name="theme-color" content="#010101">
    <meta name="robots" content="noindex, follow">
    
    <title>404 | صفحه یافت نشد</title>
    
    <!-- PWA & Icons -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="favicon-black.png">
    <link rel="apple-touch-icon" href="favicon-black.png">

    <!-- Styles -->
    <link rel="stylesheet" href="style.css">

    <!-- Tailwind -->
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
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="flex items-center justify-center min-h-screen p-4 relative overflow-hidden bg-bg text-text antialiased selection:bg-primary selection:text-white">

    <!-- Background Texture -->
    <div class="bg-noise"></div>
    
    <!-- Ambient Glow -->
    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-primary/10 rounded-full blur-[150px] animate-pulse-slow pointer-events-none"></div>
    <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-purple-900/10 rounded-full blur-[150px] animate-pulse-slow pointer-events-none" style="animation-delay: 2s;"></div>

    <!-- Main Content -->
    <div class="text-center z-10 max-w-2xl mx-auto relative px-4">
        
        <!-- 3D Hero Image -->
        <div class="relative mb-8 flex justify-center">
            <!-- Glow behind image -->
            <div class="absolute inset-0 bg-primary/20 blur-[60px] rounded-full transform scale-75 animate-pulse"></div>
            
            <!-- The 3D Asset -->
            <img src="404-notfound.webp" alt="404 Not Found" class="w-64 md:w-80 h-auto relative z-10 animate-float drop-shadow-2xl">
        </div>

        <!-- Error Message Card -->
        <div class="animate-fade-in glass-panel p-8 rounded-3xl border border-border shadow-2xl relative overflow-hidden group">
            
            <!-- Decorative line -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-error to-transparent opacity-80"></div>

            <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight mb-4">
                مسیر اشتباه است!
            </h2>
            
            <p class="text-sec text-lg leading-relaxed max-w-lg mx-auto mb-8">
                متاسفانه لینکی که به دنبال آن هستید وجود ندارد. ممکن است حذف شده باشد، منقضی شده باشد یا آدرس آن تغییر کرده باشد.
            </p>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                
                <!-- Home Button -->
                <a href="index.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-primary hover:bg-[#008F85] text-white font-bold py-4 px-8 rounded-2xl shadow-[0_0_20px_rgba(0,176,164,0.3)] hover:shadow-[0_0_30px_rgba(0,176,164,0.5)] transition-all transform hover:scale-105 active:scale-95 group">
                    <svg class="w-5 h-5 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span>بازگشت به خانه</span>
                </a>

                <!-- Contact/Report Button -->
                <a href="https://readystudio.ir/rdys-linkshortener/#report" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-card border border-border hover:border-error/50 text-sec hover:text-white font-medium py-4 px-8 rounded-2xl transition-colors group">
                    <img src="block-vip-access.webp" class="w-5 h-5 opacity-60 group-hover:opacity-100 transition-opacity" alt="Report">
                    <span>گزارش خرابی لینک</span>
                </a>
            </div>
        </div>
        
        <!-- Footer Info -->
        <div class="mt-12 opacity-30 hover:opacity-100 transition-opacity duration-500">
            <p class="text-xs font-mono text-sec flex items-center justify-center gap-2">
                <span class="w-2 h-2 rounded-full bg-error"></span>
                Error Code: 404_NOT_FOUND
            </p>
        </div>

    </div>

</body>
</html>