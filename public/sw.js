self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { body: event.data ? event.data.text() : '' };
    }

    // Всегда показываем системное уведомление — и при открытой, и при закрытой вкладке.
    // Дубли с poll-уведомлениями режутся по tag на стороне ОС.
    event.waitUntil(self.registration.showNotification(payload.title || 'TaskManagerLarVit', {
        body: payload.body || '',
        icon: payload.icon || '/favicon.ico',
        badge: payload.icon || '/favicon.ico',
        data: { url: payload.url || '/' },
        tag: payload.tag || 'tml-chat',
        renotify: true,
        requireInteraction: false,
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        for (const client of windows) {
            if ('focus' in client) {
                if (client.url.includes(url) || client.url.includes('/chats')) {
                    return client.focus().then(() => {
                        if ('navigate' in client) return client.navigate(url);
                    });
                }
            }
        }
        if (clients.openWindow) return clients.openWindow(url);
    }));
});
