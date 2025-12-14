const CACHE_NAME = 'rdys-cache-v2';
const ASSETS_TO_CACHE = [
    './',
    './index.php',
    './login.php',
    './404.php',
    './logo.svg',
    './manifest.json',
    'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css',
    'https://cdn.tailwindcss.com'
];

// 1. نصب سرویس ورکر و کش کردن فایل‌های اولیه
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

// 2. پاکسازی کش‌های قدیمی هنگام آپدیت
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keyList) => {
            return Promise.all(keyList.map((key) => {
                if (key !== CACHE_NAME) {
                    return caches.delete(key);
                }
            }));
        })
    );
    return self.clients.claim();
});

// 3. استراتژی شبکه (Network First) برای محتوای داینامیک
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // درخواست‌های API، ادمین و ریدارکت نباید کش شوند
    if (url.pathname.includes('api.php') || 
        url.pathname.includes('admin.php') || 
        url.pathname.includes('redirect.php') ||
        event.request.method === 'POST') {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .catch(() => {
                // اگر آفلاین بودیم، از کش بخوان
                return caches.match(event.request);
            })
    );
});