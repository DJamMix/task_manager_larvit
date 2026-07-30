/**
 * Кастомные тосты и диалоги (вместо alert/confirm).
 * window.uiToast(message, type?)
 * window.uiConfirm({ title, message, confirmText, cancelText, danger? }) => Promise<boolean>
 * window.uiChoice({ title, message, options:[{value,label,hint?}], defaultValue, confirmText }) => Promise<string|null>
 */
(() => {
    const ensureRoot = () => {
        let root = document.getElementById('ui-toast-root');
        if (!root) {
            root = document.createElement('div');
            root.id = 'ui-toast-root';
            root.className = 'ui-toast-root';
            root.setAttribute('aria-live', 'polite');
            document.body.appendChild(root);
        }
        return root;
    };

    const ensureOverlay = () => {
        let el = document.getElementById('ui-dialog-root');
        if (!el) {
            el = document.createElement('div');
            el.id = 'ui-dialog-root';
            el.className = 'ui-dialog-root';
            el.hidden = true;
            document.body.appendChild(el);
        }
        return el;
    };

    const escapeHtml = (s) => String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h16.9a2 2 0 001.7-3L12.7 3.9a2 2 0 00-3.4 0z"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>',
    };

    window.uiToast = (message, type = 'info', timeout = 4200) => {
        const root = ensureRoot();
        const el = document.createElement('div');
        const kind = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
        el.className = 'ui-toast ui-toast--' + kind;
        el.innerHTML =
            '<span class="ui-toast__icon" aria-hidden="true">' + (icons[kind] || icons.info) + '</span>' +
            '<div class="ui-toast__body">' + escapeHtml(message) + '</div>' +
            '<button type="button" class="ui-toast__close" aria-label="Закрыть">×</button>';

        const remove = () => {
            el.classList.add('is-leaving');
            setTimeout(() => el.remove(), 220);
        };
        el.querySelector('.ui-toast__close')?.addEventListener('click', remove);
        root.appendChild(el);
        requestAnimationFrame(() => el.classList.add('is-in'));
        if (timeout > 0) setTimeout(remove, timeout);
        return el;
    };

    window.uiConfirm = (opts = {}) => new Promise((resolve) => {
        const root = ensureOverlay();
        const title = opts.title || 'Подтверждение';
        const message = opts.message || '';
        const confirmText = opts.confirmText || 'OK';
        const cancelText = opts.cancelText || 'Отмена';
        const danger = !!opts.danger;

        root.hidden = false;
        root.innerHTML =
            '<button type="button" class="ui-dialog__backdrop" data-ui-cancel aria-label="Закрыть"></button>' +
            '<div class="ui-dialog__panel" role="dialog" aria-modal="true">' +
            '  <div class="ui-dialog__title">' + escapeHtml(title) + '</div>' +
            (message ? '<div class="ui-dialog__text">' + escapeHtml(message) + '</div>' : '') +
            '  <div class="ui-dialog__actions">' +
            '    <button type="button" class="ui-dialog__btn ui-dialog__btn--ghost" data-ui-cancel>' + escapeHtml(cancelText) + '</button>' +
            '    <button type="button" class="ui-dialog__btn ' + (danger ? 'ui-dialog__btn--danger' : 'ui-dialog__btn--primary') + '" data-ui-ok>' + escapeHtml(confirmText) + '</button>' +
            '  </div>' +
            '</div>';

        const done = (value) => {
            root.hidden = true;
            root.innerHTML = '';
            resolve(value);
        };
        root.querySelectorAll('[data-ui-cancel]').forEach((b) => b.addEventListener('click', () => done(false)));
        root.querySelector('[data-ui-ok]')?.addEventListener('click', () => done(true));
    });

    window.uiChoice = (opts = {}) => new Promise((resolve) => {
        const root = ensureOverlay();
        const title = opts.title || 'Выберите действие';
        const message = opts.message || '';
        const options = Array.isArray(opts.options) ? opts.options : [];
        const defaultValue = opts.defaultValue ?? options[0]?.value;
        const confirmText = opts.confirmText || 'Продолжить';
        const cancelText = opts.cancelText || 'Отмена';
        const danger = !!opts.danger;

        const radios = options.map((o, i) => {
            const id = 'ui-choice-' + i;
            const checked = String(o.value) === String(defaultValue) ? ' checked' : '';
            return (
                '<label class="ui-choice" for="' + id + '">' +
                '<input type="radio" name="ui-choice" id="' + id + '" value="' + escapeHtml(o.value) + '"' + checked + '>' +
                '<span class="ui-choice__mark"></span>' +
                '<span class="ui-choice__text">' +
                '<strong>' + escapeHtml(o.label || '') + '</strong>' +
                (o.hint ? '<small>' + escapeHtml(o.hint) + '</small>' : '') +
                '</span></label>'
            );
        }).join('');

        root.hidden = false;
        root.innerHTML =
            '<button type="button" class="ui-dialog__backdrop" data-ui-cancel aria-label="Закрыть"></button>' +
            '<div class="ui-dialog__panel" role="dialog" aria-modal="true">' +
            '  <div class="ui-dialog__title">' + escapeHtml(title) + '</div>' +
            (message ? '<div class="ui-dialog__text">' + escapeHtml(message) + '</div>' : '') +
            '  <div class="ui-choice-list">' + radios + '</div>' +
            '  <div class="ui-dialog__actions">' +
            '    <button type="button" class="ui-dialog__btn ui-dialog__btn--ghost" data-ui-cancel>' + escapeHtml(cancelText) + '</button>' +
            '    <button type="button" class="ui-dialog__btn ' + (danger ? 'ui-dialog__btn--danger' : 'ui-dialog__btn--primary') + '" data-ui-ok>' + escapeHtml(confirmText) + '</button>' +
            '  </div>' +
            '</div>';

        const done = (value) => {
            root.hidden = true;
            root.innerHTML = '';
            resolve(value);
        };
        root.querySelectorAll('[data-ui-cancel]').forEach((b) => b.addEventListener('click', () => done(null)));
        root.querySelector('[data-ui-ok]')?.addEventListener('click', () => {
            const picked = root.querySelector('input[name="ui-choice"]:checked');
            done(picked ? picked.value : (defaultValue ?? null));
        });
    });
})();
