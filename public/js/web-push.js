(() => {
    const button = document.getElementById('bx-enable-push');
    const statusEl = document.getElementById('bx-push-status');
    const actionLabel = document.getElementById('bx-push-action-label');
    const hintEl = document.getElementById('bx-push-hint');
    const messenger = document.querySelector('.bx-messenger');
    const csrf = document.querySelector('meta[name="csrf_token"]')?.content
        || document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || messenger?.getAttribute('data-csrf')
        || '';
    const configured = messenger?.getAttribute('data-push-configured') === '1';

    const base64UrlToUint8Array = (value) => {
        const padded = value + '='.repeat((4 - (value.length % 4)) % 4);
        const base64 = padded.replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
    };

    const request = (url, options = {}) => fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        ...options,
    });

    const supported = () => ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);

    const setUi = (state, detail = '') => {
        if (statusEl) {
            statusEl.textContent = state;
            statusEl.dataset.state = state;
        }
        if (actionLabel) actionLabel.textContent = detail || actionLabel.textContent;
        if (hintEl && detail && state === 'Ошибка') hintEl.textContent = detail;
    };

    const getRegistration = async () => {
        const existing = await navigator.serviceWorker.getRegistration('/');
        if (existing) return existing;
        return navigator.serviceWorker.register('/sw.js', { scope: '/' });
    };

    const syncSubscription = async () => {
        if (!supported() || !configured) return null;
        const keyUrl = messenger?.getAttribute('data-vapid-key-url');
        const subscribeUrl = messenger?.getAttribute('data-push-subscribe-url');
        if (!keyUrl || !subscribeUrl) return null;

        const keyResponse = await request(keyUrl);
        const keyPayload = await keyResponse.json();
        if (!keyResponse.ok || !keyPayload.public_key) {
            throw new Error('VAPID-ключ не настроен на сервере');
        }

        const registration = await getRegistration();
        await navigator.serviceWorker.ready;

        let subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlToUint8Array(keyPayload.public_key),
            });
        }

        const res = await request(subscribeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(subscription.toJSON()),
        });
        if (!res.ok) throw new Error('Не удалось сохранить подписку');
        return subscription;
    };

    const unsubscribe = async () => {
        if (!supported()) return;
        const registration = await getRegistration();
        const subscription = await registration.pushManager.getSubscription();
        if (!subscription) return;
        const endpoint = subscription.endpoint;
        await subscription.unsubscribe();
        const url = messenger?.getAttribute('data-push-unsubscribe-url');
        if (url) {
            await request(url, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint }),
            });
        }
    };

    const refreshUi = async () => {
        if (!button) return;
        if (!configured) {
            setUi('Не настроено', 'Включить push');
            button.disabled = true;
            if (hintEl) hintEl.textContent = 'На сервере нет VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY в .env';
            return;
        }
        if (!supported()) {
            setUi('Нет поддержки', 'Недоступно');
            button.disabled = true;
            if (hintEl) hintEl.textContent = 'Браузер не поддерживает Web Push (нужен Chrome/Firefox/Edge и HTTPS).';
            return;
        }
        button.disabled = false;
        if (Notification.permission === 'denied') {
            setUi('Запрещено', 'Разрешить в браузере');
            if (hintEl) hintEl.textContent = 'Уведомления запрещены. Разрешите их в настройках сайта браузера (и в Linux — в системных уведомлениях).';
            return;
        }
        try {
            const registration = await getRegistration();
            const subscription = await registration.pushManager.getSubscription();
            if (Notification.permission === 'granted' && subscription) {
                setUi('Включены', 'Отключить push');
                button.dataset.enabled = '1';
            } else {
                setUi('Выключены', 'Включить push');
                button.dataset.enabled = '0';
            }
        } catch (e) {
            setUi('Выключены', 'Включить push');
            button.dataset.enabled = '0';
        }
    };

    button?.addEventListener('click', async () => {
        try {
            button.disabled = true;
            if (button.dataset.enabled === '1') {
                await unsubscribe();
            } else {
                if (Notification.permission === 'default') {
                    await Notification.requestPermission();
                }
                if (Notification.permission !== 'granted') {
                    throw new Error('Разрешите уведомления в браузере');
                }
                await syncSubscription();
            }
        } catch (error) {
            console.warn('Web Push:', error);
            setUi('Ошибка', error?.message || 'Не удалось изменить push');
            alert(error?.message || 'Не удалось изменить push-уведомления');
        } finally {
            button.disabled = false;
            await refreshUi();
        }
    });

    refreshUi();
    if (configured && supported() && Notification.permission === 'granted') {
        syncSubscription().then(refreshUi).catch(() => refreshUi());
    }
})();
