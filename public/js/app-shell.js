(function () {
    const KEY = 'crewdev.aside.collapsed';
    const CLASS = 'crewdev-aside-collapsed';

    function readLocal() {
        try {
            return localStorage.getItem(KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function writeLocal(collapsed) {
        try {
            localStorage.setItem(KEY, collapsed ? '1' : '0');
        } catch (e) {}
    }

    function apply(collapsed) {
        document.body.classList.toggle(CLASS, !!collapsed);
        const btn = document.getElementById('crewdev-aside-toggle');
        if (btn) {
            btn.title = collapsed ? 'Развернуть меню' : 'Свернуть меню';
            btn.setAttribute('aria-label', btn.title);
        }
    }

    // Мгновенно из localStorage, чтобы не мигало
    const serverFlag = window.__crewdevUi && typeof window.__crewdevUi.sidebarCollapsed === 'boolean'
        ? window.__crewdevUi.sidebarCollapsed
        : null;
    const initial = serverFlag !== null ? serverFlag : readLocal();
    apply(initial);
    writeLocal(initial);

    document.addEventListener('DOMContentLoaded', () => {
        apply(readLocal() || (serverFlag === true));
        const btn = document.getElementById('crewdev-aside-toggle');
        if (!btn) return;

        btn.addEventListener('click', async () => {
            const next = !document.body.classList.contains(CLASS);
            apply(next);
            writeLocal(next);

            const url = window.__crewdevUi && window.__crewdevUi.prefsUrl;
            const csrf = window.__crewdevUi && window.__crewdevUi.csrf;
            if (!url || !csrf) return;

            try {
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ sidebar_collapsed: next }),
                    credentials: 'same-origin',
                });
            } catch (e) {}
        });
    });
})();
