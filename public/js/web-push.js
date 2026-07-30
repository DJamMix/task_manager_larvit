(() => {
    const VAPID_LS_KEY = 'tml_vapid_public';
    const ENABLED_FLAG = 'tml_push_enabled';

    const readCookie = (name) => {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    };

    const base64UrlToUint8Array = (value) => {
        const padded = value + '='.repeat((4 - (value.length % 4)) % 4);
        const base64 = padded.replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
    };

    const supported = () =>
        typeof window !== 'undefined'
        && ('Notification' in window)
        && ('serviceWorker' in navigator)
        && ('PushManager' in window);

    const isSecure = () => window.isSecureContext === true
        || location.protocol === 'https:'
        || location.hostname === 'localhost'
        || location.hostname === '127.0.0.1';

    const paintQuickStatus = () => {
        const button = document.getElementById('bx-enable-push');
        const statusEl = document.getElementById('bx-push-status');
        const actionLabel = document.getElementById('bx-push-action-label');
        const testButton = document.getElementById('bx-test-push');
        const hintEl = document.getElementById('bx-push-hint');
        if (!button || !statusEl) return;

        if (hintEl) {
            hintEl.hidden = true;
            hintEl.textContent = '';
        }

        if (!supported()) {
            statusEl.textContent = 'Нет поддержки';
            if (actionLabel) actionLabel.textContent = 'Недоступно';
            button.disabled = true;
            button.dataset.enabled = '0';
            return;
        }
        if (!isSecure()) {
            statusEl.textContent = 'Нужен HTTPS';
            if (actionLabel) actionLabel.textContent = 'Недоступно';
            button.disabled = true;
            button.dataset.enabled = '0';
            return;
        }

        button.disabled = false;
        if (Notification.permission === 'denied') {
            statusEl.textContent = 'Запрещено';
            if (actionLabel) actionLabel.textContent = 'Открыть подсказку';
            button.dataset.enabled = '0';
            return;
        }

        const on = localStorage.getItem(ENABLED_FLAG) === '1' && Notification.permission === 'granted';
        if (on) {
            statusEl.textContent = 'Включены';
            if (actionLabel) actionLabel.textContent = 'Отключить push';
            button.dataset.enabled = '1';
            if (testButton) testButton.hidden = false;
            return;
        }

        if (Notification.permission === 'granted') {
            statusEl.textContent = 'Проверка…';
            if (actionLabel) actionLabel.textContent = 'Включить push';
            button.dataset.enabled = '0';
            return;
        }

        statusEl.textContent = 'Выключены';
        if (actionLabel) actionLabel.textContent = 'Включить push';
        button.dataset.enabled = '0';
        if (testButton) testButton.hidden = true;
    };

    const initPushUi = () => {
        const button = document.getElementById('bx-enable-push');
        const testButton = document.getElementById('bx-test-push');
        const statusEl = document.getElementById('bx-push-status');
        const actionLabel = document.getElementById('bx-push-action-label');
        const hintEl = document.getElementById('bx-push-hint');
        const messenger = document.querySelector('.bx-messenger');
        if (!button || !messenger) return;

        paintQuickStatus();

        if (button.dataset.pushBound === '1') {
            window.__bxRefreshPushUi?.();
            return;
        }
        button.dataset.pushBound = '1';

        if (hintEl) {
            hintEl.hidden = true;
            hintEl.textContent = '';
        }

        const csrfToken = () =>
            messenger.getAttribute('data-csrf')
            || document.querySelector('#post-form input[name="_token"]')?.value
            || document.querySelector('input[name="_token"]')?.value
            || document.querySelector('meta[name="csrf_token"]')?.content
            || document.querySelector('meta[name="csrf-token"]')?.content
            || '';

        const markConfigured = (value) => {
            messenger.setAttribute('data-push-configured', value ? '1' : '0');
        };

        const request = (url, options = {}) => {
            const token = csrfToken();
            const xsrf = readCookie('XSRF-TOKEN');
            const headers = {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                ...(options.headers || {}),
            };
            return fetch(url, {
                credentials: 'same-origin',
                ...options,
                headers,
            });
        };

        const setUi = (state, actionText) => {
            if (statusEl) {
                statusEl.textContent = state;
                statusEl.dataset.state = state;
            }
            if (actionLabel && actionText) actionLabel.textContent = actionText;
            if (testButton) {
                testButton.hidden = !(Notification.permission === 'granted' && button.dataset.enabled === '1');
            }
        };

        const askBrowserPermission = () => {
            if (!('Notification' in window)) return Promise.resolve('denied');
            if (Notification.permission !== 'default') return Promise.resolve(Notification.permission);
            try {
                return Promise.resolve(Notification.requestPermission());
            } catch (e) {
                return new Promise((resolve) => {
                    Notification.requestPermission((permission) => resolve(permission));
                });
            }
        };

        const getRegistration = async () => {
            let existing = null;
            try {
                existing = await navigator.serviceWorker.getRegistration('/');
            } catch (e) {
                existing = null;
            }
            if (existing) {
                existing.update().catch(() => {});
                try {
                    await Promise.race([
                        navigator.serviceWorker.ready,
                        new Promise((resolve) => setTimeout(resolve, 1500)),
                    ]);
                } catch (e) {}
                return existing;
            }
            const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            await Promise.race([
                navigator.serviceWorker.ready,
                new Promise((resolve) => setTimeout(resolve, 2000)),
            ]);
            return registration;
        };

        const fetchPublicKey = async () => {
            const keyUrl = messenger.getAttribute('data-vapid-key-url');
            if (!keyUrl) throw new Error('Маршруты Web Push не найдены');
            const keyResponse = await request(keyUrl);
            const keyPayload = await keyResponse.json().catch(() => ({}));
            if (!keyResponse.ok || !keyPayload.public_key) {
                throw new Error('VAPID-ключ пустой. Обновите страницу.');
            }
            if (keyPayload.configured) markConfigured(true);
            return String(keyPayload.public_key);
        };

        const syncSubscription = async ({ force = false } = {}) => {
            const subscribeUrl = messenger.getAttribute('data-push-subscribe-url');
            if (!subscribeUrl) throw new Error('Маршруты Web Push не найдены');

            const publicKey = await fetchPublicKey();
            const registration = await getRegistration();
            let subscription = await registration.pushManager.getSubscription();
            const storedKey = localStorage.getItem(VAPID_LS_KEY) || '';

            if (subscription && (force || storedKey !== publicKey)) {
                try { await subscription.unsubscribe(); } catch (e) {}
                subscription = null;
            }

            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: base64UrlToUint8Array(publicKey),
                });
            }

            const payload = subscription.toJSON();
            payload._token = csrfToken();

            const res = await request(subscribeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                const fromErrors = err.errors
                    ? Object.values(err.errors).flat().filter(Boolean).join(' ')
                    : '';
                throw new Error(err.message || fromErrors || 'Не удалось сохранить подписку на сервере');
            }

            localStorage.setItem(VAPID_LS_KEY, publicKey);
            localStorage.setItem(ENABLED_FLAG, '1');
            return subscription;
        };

        const unsubscribe = async () => {
            const registration = await getRegistration();
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                const endpoint = subscription.endpoint;
                await subscription.unsubscribe();
                const url = messenger.getAttribute('data-push-unsubscribe-url');
                if (url) {
                    await request(url, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ endpoint, _token: csrfToken() }),
                    });
                }
            }
            localStorage.removeItem(VAPID_LS_KEY);
            localStorage.removeItem(ENABLED_FLAG);
        };

        const showTestNotification = (title, body) => {
            try {
                const n = new Notification(title || 'Уведомления включены', {
                    body: body || 'Так будут приходить сообщения из чатов.',
                    icon: '/favicon.ico',
                    tag: 'tml-push-test',
                });
                setTimeout(() => n.close(), 6000);
            } catch (e) {}
        };

        const refreshUi = async () => {
            paintQuickStatus();

            if (!supported() || !isSecure() || Notification.permission === 'denied') return;

            try {
                const registration = await getRegistration();
                const subscription = await registration.pushManager.getSubscription();
                if (Notification.permission === 'granted' && subscription) {
                    localStorage.setItem(ENABLED_FLAG, '1');
                    button.dataset.enabled = '1';
                    setUi('Включены', 'Отключить push');
                    return;
                }
                if (Notification.permission === 'granted' && !subscription && localStorage.getItem(ENABLED_FLAG) === '1') {
                    try {
                        await syncSubscription({ force: false });
                        button.dataset.enabled = '1';
                        setUi('Включены', 'Отключить push');
                        return;
                    } catch (e) {
                        localStorage.removeItem(ENABLED_FLAG);
                    }
                }
            } catch (e) {
                if (localStorage.getItem(ENABLED_FLAG) === '1' && Notification.permission === 'granted') {
                    button.dataset.enabled = '1';
                    setUi('Включены', 'Отключить push');
                    return;
                }
            }

            if (Notification.permission !== 'granted') {
                localStorage.removeItem(ENABLED_FLAG);
            }
            button.dataset.enabled = '0';
            setUi(
                Notification.permission === 'granted' ? 'Без подписки' : 'Выключены',
                'Включить push'
            );
        };

        window.__bxRefreshPushUi = refreshUi;

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
                        setUi('Запрещено', 'Открыть подсказку');
                        alert('Разрешение отклонено. Включите уведомления в настройках сайта браузера и повторите.');
                        return;
                    }
                    if (permission !== 'granted') {
                        throw new Error('Разрешение не получено. Нажмите «Включить push» ещё раз и выберите «Разрешить».');
                    }

                    await syncSubscription({ force: true });
                    showTestNotification();
                    alert('Push включены. Можно нажать «Проверить push». Свои сообщения себе не приходят.');
                } catch (error) {
                    console.warn('Web Push:', error);
                    alert(error?.message || 'Не удалось включить push-уведомления');
                    setUi('Ошибка', 'Повторить');
                } finally {
                    button.disabled = false;
                    await refreshUi();
                }
            })();
        });

        if (testButton && testButton.dataset.pushBound !== '1') {
            testButton.dataset.pushBound = '1';
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
                        if (!res.ok) throw new Error(payload.message || 'Сервер не смог отправить push');
                        alert('Тестовый push отправлен (' + (payload.subscriptions || 1) + ' подписка).');
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
    };

    const boot = () => {
        paintQuickStatus();
        initPushUi();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('turbo:load', boot);
    document.addEventListener('turbo:frame-load', boot);
})();
