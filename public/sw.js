self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : {};
    event.waitUntil(self.registration.showNotification(payload.title || 'TaskManagerLarVit', {
        body: payload.body || '',
        icon: payload.icon || '/favicon.ico',
        data: { url: payload.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const existing = windows.find((client) => client.url === url);
        return existing ? existing.focus() : clients.openWindow(url);
    }));
});
