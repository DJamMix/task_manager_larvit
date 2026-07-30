(() => {
    const button = document.getElementById('bx-enable-push');
    const messenger = document.querySelector('.bx-messenger');
    const csrf = document.querySelector('meta[name="csrf_token"]')?.content
        || document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || '';
    const base64UrlToUint8Array = (value) => {
        const padded = value + '='.repeat((4 - value.length % 4) % 4);
        const base64 = padded.replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
    };
    const request = (url, options = {}) => fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) },
        ...options,
    });
    const syncSubscription = async () => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        const keyUrl = messenger?.getAttribute('data-vapid-key-url');
        const subscribeUrl = messenger?.getAttribute('data-push-subscribe-url');
        if (!keyUrl || !subscribeUrl) return;
        const keyResponse = await request(keyUrl);
        const keyPayload = await keyResponse.json();
        if (!keyResponse.ok || !keyPayload.public_key) return;
        const registration = await navigator.serviceWorker.register('/sw.js');
        let subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlToUint8Array(keyPayload.public_key),
            });
        }
        await request(subscribeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(subscription.toJSON()),
        });
    };
    const updateButton = () => {
        if (!button || !('Notification' in window) || !('PushManager' in window)) return;
        button.hidden = Notification.permission === 'granted';
    };

    button?.addEventListener('click', async () => {
        try {
            if (Notification.permission === 'default') await Notification.requestPermission();
            if (Notification.permission === 'granted') await syncSubscription();
        } catch (error) {
            console.warn('Не удалось включить Web Push', error);
        } finally {
            updateButton();
        }
    });

    updateButton();
    if (Notification.permission === 'granted') syncSubscription().catch(() => {});
})();
