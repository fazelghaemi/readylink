/**
 * RDYS Service Worker (Enterprise Grade)
 * Version: 6.0.0
 * Strategy: 
 * - Assets (CSS, JS, Fonts, Images): Stale-While-Revalidate (Fastest)
 * - HTML Pages: Network First (Fresh content preference)
 * - API & Admin: Network Only (Security & Accuracy)
 */

const CACHE_NAME = 'rdys-cache-v6-core';
const DYNAMIC_CACHE = 'rdys-cache-v6-dynamic';

// فایل‌هایی که باید فوراً کش شوند (App Shell)
const STATIC_ASSETS = [
    './',
    './index.php',
    './login.php',
    './register.php',
    './404.php',
    './style.css',
    './manifest.json',
    './favicon-black.png',
    './logo.svg',
    './readyfont.woff',
    // تصاویر سه بعدی (اگر وجود دارند کش شوند)
    './404-notfound.webp',
    './upgrade-account-3d-icon.webp',
    './block-vip-access.webp',
    './loading-hub.webp',
    './readystudio-avatar.jpg',
    './3d-link.png',
    './3d-rocket.png',
    './3d-globe.png'
];

// لیست مسیرهایی که هرگز نباید کش شوند (API و عملیات حساس)
const BLACKLIST_URLS = [
    '/api.php',
    '/admin.php',
    '/redirect.php',
    '/install.php',
    '/export.php'
];

// 1. نصب و کش کردن فایل‌های اولیه
self.addEventListener('install', (event) => {
    // اجبار به آپدیت فوری SW بدون منتظر ماندن برای بسته شدن تب‌ها
    self.skipWaiting();
    
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[SW] Pre-caching App Shell');
            return cache.addAll(STATIC_ASSETS);
        })
    );
});

// 2. فعال‌سازی و پاک کردن کش‌های قدیمی
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keyList) => {
            return Promise.all(keyList.map((key) => {
                if (key !== CACHE_NAME && key !== DYNAMIC_CACHE) {
                    console.log('[SW] Removing old cache:', key);
                    return caches.delete(key);
                }
            }));
        })
    );
    return self.clients.claim();
});

// 3. مدیریت درخواست‌ها (Fetch Strategy)
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // الف) اگر درخواست مربوط به API یا ادمین است، فقط از شبکه بگیر (Network Only)
    if (event.request.method === 'POST' || BLACKLIST_URLS.some(path => url.pathname.includes(path))) {
        return; // Default network behavior
    }

    // ب) اگر درخواست مربوط به فایل‌های استاتیک است (CSS, JS, Images, Fonts)
    // استراتژی: Stale-While-Revalidate
    // (فورا از کش نشان بده، و در پس‌زمینه نسخه جدید را دانلود و کش را آپدیت کن)
    if (url.pathname.match(/\.(css|js|png|jpg|jpeg|svg|webp|woff|woff2)$/)) {
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                const networkFetch = fetch(event.request).then((networkResponse) => {
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, networkResponse.clone());
                    });
                    return networkResponse;
                });
                return cachedResponse || networkFetch;
            })
        );
        return;
    }

    // پ) اگر درخواست مربوط به صفحات HTML است (Navigation)
    // استراتژی: Network First, falling back to Cache
    // (تلاش کن نسخه تازه را بگیری، اگر آفلاین بودی، نسخه کش شده را نشان بده)
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .then((networkResponse) => {
                    return caches.open(DYNAMIC_CACHE).then((cache) => {
                        cache.put(event.request, networkResponse.clone());
                        return networkResponse;
                    });
                })
                .catch(() => {
                    // اگر آفلاین بود، صفحه کش شده را نشان بده
                    return caches.match(event.request).then((cachedResponse) => {
                        if (cachedResponse) return cachedResponse;
                        // اگر صفحه در کش نبود، صفحه آفلاین اختصاصی (یا 404) را نشان بده
                        return caches.match('./404.php');
                    });
                })
        );
        return;
    }
});

// 4. مدیریت پیام‌های Push (برای توسعه آینده)
self.addEventListener('push', (event) => {
    // Placeholder for future notification features
});