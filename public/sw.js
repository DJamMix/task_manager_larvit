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

    const title = payload.title || 'TaskManagerLarVit';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/favicon.ico',
        badge: payload.icon || '/favicon.ico',
        data: { url: payload.url || '/' },
        tag: payload.tag || 'tml-chat',
        renotify: true,
        requireInteraction: false,
    };

    event.waitUntil((async () => {
        // Не дублируем, если пользователь уже смотрит этот чат в активной вкладке
        try {
            const windows = await clients.matchAll({ type: 'window', includeUncontrolled: true });
            const target = String(payload.url || '');
            const chatMatch = target.match(/\/chats\/(\d+)/);
            const chatId = chatMatch ? chatMatch[1] : '';
            for (const client of windows) {
                if (!client.focused) continue;
                const href = String(client.url || '');
                if (chatId && (href.includes('/chats/' + chatId) || href.endsWith('/' + chatId))) {
                    return;
                }
            }
        } catch (e) {}

        await self.registration.showNotification(title, options);
    })());
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
