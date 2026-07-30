(() => {
    const button = document.getElementById('bx-enable-push');
    const testButton = document.getElementById('bx-test-push');
    const statusEl = document.getElementById('bx-push-status');
    const actionLabel = document.getElementById('bx-push-action-label');
    const hintEl = document.getElementById('bx-push-hint');
    const messenger = document.querySelector('.bx-messenger');
    if (!button || !messenger) return;

    const VAPID_LS_KEY = 'tml_vapid_public';

    const csrf = document.querySelector('meta[name="csrf_token"]')?.content
        || document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || messenger.getAttribute('data-csrf')
        || '';

    const configured = () => messenger.getAttribute('data-push-configured') === '1';

    const markConfigured = (value) => {
        messenger.setAttribute('data-push-configured', value ? '1' : '0');
    };

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

    const supported = () =>
        typeof window !== 'undefined'
        && ('Notification' in window)
        && ('serviceWorker' in navigator)
        && ('PushManager' in window);

    const isSecure = () => window.isSecureContext === true
        || location.protocol === 'https:'
        || location.hostname === 'localhost'
        || location.hostname === '127.0.0.1';

    const setUi = (state, actionText, hint) => {
        if (statusEl) {
            statusEl.textContent = state;
            statusEl.dataset.state = state;
        }
        if (actionLabel && actionText) actionLabel.textContent = actionText;
        if (hintEl && hint) hintEl.textContent = hint;
        if (testButton) {
            testButton.hidden = !(Notification.permission === 'granted' && button.dataset.enabled === '1');
        }
    };

    const askBrowserPermission = () => {
        if (!('Notification' in window)) {
            return Promise.resolve('denied');
        }
        if (Notification.permission !== 'default') {
            return Promise.resolve(Notification.permission);
        }
        try {
            return Promise.resolve(Notification.requestPermission());
        } catch (e) {
            return new Promise((resolve) => {
                Notification.requestPermission((permission) => resolve(permission));
            });
        }
    };

    const getRegistration = async () => {
        const existing = await navigator.serviceWorker.getRegistration('/');
        if (existing) {
            try { await existing.update(); } catch (e) {}
            await navigator.serviceWorker.ready;
            return existing;
        }
        const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        await navigator.serviceWorker.ready;
        return registration;
    };

    const fetchPublicKey = async () => {
        const keyUrl = messenger.getAttribute('data-vapid-key-url');
        if (!keyUrl) throw new Error('Маршруты Web Push не найдены');

        const keyResponse = await request(keyUrl);
        const keyPayload = await keyResponse.json().catch(() => ({}));
        if (!keyResponse.ok || !keyPayload.public_key) {
            throw new Error('VAPID-ключ пустой. Обновите страницу или проверьте storage/app/webpush-vapid.json');
        }
        if (keyPayload.configured) {
            markConfigured(true);
        }
        return String(keyPayload.public_key);
    };

    const syncSubscription = async ({ force = false } = {}) => {
        const subscribeUrl = messenger.getAttribute('data-push-subscribe-url');
        if (!subscribeUrl) {
            throw new Error('Маршруты Web Push не найдены');
        }

        const publicKey = await fetchPublicKey();
        const registration = await getRegistration();
        let subscription = await registration.pushManager.getSubscription();
        const storedKey = localStorage.getItem(VAPID_LS_KEY) || '';

        if (subscription && (force || storedKey !== publicKey)) {
            try {
                await subscription.unsubscribe();
            } catch (e) {
                // ignore
            }
            subscription = null;
        }

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlToUint8Array(publicKey),
            });
        }

        const res = await request(subscribeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(subscription.toJSON()),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            const fromErrors = err.errors
                ? Object.values(err.errors).flat().filter(Boolean).join(' ')
                : '';
            throw new Error(err.message || fromErrors || 'Не удалось сохранить подписку на сервере');
        }

        localStorage.setItem(VAPID_LS_KEY, publicKey);
        return subscription;
    };

    const unsubscribe = async () => {
        const registration = await getRegistration();
        const subscription = await registration.pushManager.getSubscription();
        if (!subscription) return;
        const endpoint = subscription.endpoint;
        await subscription.unsubscribe();
        const url = messenger.getAttribute('data-push-unsubscribe-url');
        if (url) {
            await request(url, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint }),
            });
        }
        localStorage.removeItem(VAPID_LS_KEY);
    };

    const showTestNotification = (title, body) => {
        try {
            const n = new Notification(title || 'Уведомления включены', {
                body: body || 'Так будут приходить сообщения из чатов.',
                icon: '/favicon.ico',
                tag: 'tml-push-test',
            });
            setTimeout(() => n.close(), 6000);
        } catch (e) {
            // ignore
        }
    };

    const refreshUi = async () => {
        if (!supported()) {
            setUi(
                'Нет поддержки',
                'Недоступно',
                'Браузер не поддерживает Web Push. Нужен Chrome, Firefox или Edge.'
            );
            button.disabled = true;
            button.dataset.enabled = '0';
            return;
        }
        if (!isSecure()) {
            setUi(
                'Нужен HTTPS',
                'Недоступно',
                'Браузер показывает разрешение только на HTTPS (или localhost).'
            );
            button.disabled = true;
            button.dataset.enabled = '0';
            return;
        }

        button.disabled = false;

        if (Notification.permission === 'denied') {
            setUi(
                'Запрещено',
                'Открыть подсказку',
                'Уведомления запрещены для сайта. В адресной строке → значок замка → «Уведомления» → «Разрешить», затем нажмите снова.'
            );
            button.dataset.enabled = '0';
            return;
        }

        try {
            const registration = await getRegistration();
            const subscription = await registration.pushManager.getSubscription();
            if (Notification.permission === 'granted' && subscription) {
                setUi(
                    'Включены',
                    'Отключить push',
                    'Push активны. Нажмите «Проверить push», чтобы убедиться, что сервер доставляет уведомления.'
                );
                button.dataset.enabled = '1';
                return;
            }
        } catch (e) {
            // ignore
        }

        setUi(
            Notification.permission === 'granted' ? 'Без подписки' : 'Выключены',
            'Включить push',
            'Нажмите «Включить push» — браузер покажет запрос разрешения.'
        );
        button.dataset.enabled = '0';
    };

    button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (!supported()) {
            alert('Браузер не поддерживает push-уведомления.');
            return;
        }
        if (!isSecure()) {
            alert('Разрешение на уведомления доступно только по HTTPS (или на localhost).');
            return;
        }

        const permissionPromise = askBrowserPermission();

        (async () => {
            button.disabled = true;
            try {
                if (button.dataset.enabled === '1') {
                    await unsubscribe();
                    alert('Push-уведомления отключены на этом устройстве.');
                    return;
                }

                const permission = await permissionPromise;

                if (permission === 'denied') {
                    setUi(
                        'Запрещено',
                        'Открыть подсказку',
                        'Вы отклонили запрос. Разрешите уведомления в настройках сайта (замок в адресной строке) и нажмите снова.'
                    );
                    alert('Разрешение отклонено. Включите уведомления в настройках сайта браузера и повторите.');
                    return;
                }

                if (permission !== 'granted') {
                    throw new Error('Разрешение не получено. Нажмите «Включить push» ещё раз и выберите «Разрешить».');
                }

                await syncSubscription({ force: true });
                showTestNotification();
                alert('Push включены. Нажмите «Проверить push», чтобы проверить доставку с сервера. Свои сообщения не приходят — нужен второй пользователь или кнопка проверки.');
            } catch (error) {
                console.warn('Web Push:', error);
                const msg = error?.message || 'Не удалось включить push-уведомления';
                setUi('Ошибка', 'Повторить', msg);
                alert(msg);
            } finally {
                button.disabled = false;
                await refreshUi();
            }
        })();
    });

    if (testButton) {
        testButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            (async () => {
                testButton.disabled = true;
                try {
                    await syncSubscription({ force: false });
                    showTestNotification('Локальная проверка', 'Браузерное разрешение работает.');
                    const testUrl = messenger.getAttribute('data-push-test-url');
                    if (!testUrl) throw new Error('Маршрут проверки push не найден');
                    const res = await request(testUrl, { method: 'POST' });
                    const payload = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        throw new Error(payload.message || 'Сервер не смог отправить push');
                    }
                    alert('Тестовый push отправлен (' + (payload.subscriptions || 1) + ' подписка). Должно появиться системное уведомление.');
                } catch (error) {
                    console.warn('Web Push test:', error);
                    alert(error?.message || 'Не удалось проверить push');
                } finally {
                    testButton.disabled = false;
                    await refreshUi();
                }
            })();
        });
    }

    refreshUi();

    if (supported() && isSecure() && Notification.permission === 'granted') {
        syncSubscription({ force: false }).then(refreshUi).catch(() => refreshUi());
    }
})();
