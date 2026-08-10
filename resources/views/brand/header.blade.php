<div class="crewdev-aside-brand-wrap">
    <a href="{{ route('platform.index') }}" class="crewdev-aside-brand" title="CrewDev">
        <span class="crewdev-aside-brand__mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M12 8V16" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M8 12H16" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
        <span class="crewdev-aside-brand__text">CrewDev</span>
    </a>
    <button type="button"
            class="crewdev-aside-toggle"
            id="crewdev-aside-toggle"
            title="Свернуть меню"
            aria-label="Свернуть меню"
            onclick="window.crewdevToggleAside && window.crewdevToggleAside(event)">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
            <path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>
</div>
@auth
@php
    $sidebarCollapsed = false;
    try {
        $sidebarCollapsed = (bool) auth()->user()->uiPreference('sidebar_collapsed', false);
    } catch (\Throwable) {
        $sidebarCollapsed = false;
    }
@endphp
<script>
window.__crewdevUi = window.__crewdevUi || {
    sidebarCollapsed: @json($sidebarCollapsed),
    prefsUrl: @json(route('platform.ui.preferences')),
    csrf: @json(csrf_token())
};

(function () {
    var KEY = 'crewdev.aside.collapsed';
    var CLASS = 'crewdev-aside-collapsed';

    function readLocal() {
        try { return localStorage.getItem(KEY); } catch (e) { return null; }
    }

    function writeLocal(collapsed) {
        try { localStorage.setItem(KEY, collapsed ? '1' : '0'); } catch (e) {}
    }

    function apply(collapsed) {
        collapsed = !!collapsed;
        var root = document.documentElement;
        var body = document.body;
        if (root) root.classList.toggle(CLASS, collapsed);
        if (body) body.classList.toggle(CLASS, collapsed);

        var btn = document.getElementById('crewdev-aside-toggle');
        if (btn) {
            btn.title = collapsed ? 'Развернуть меню' : 'Свернуть меню';
            btn.setAttribute('aria-label', btn.title);
            btn.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        }
    }

    function current() {
        return !!(document.body && document.body.classList.contains(CLASS))
            || !!(document.documentElement && document.documentElement.classList.contains(CLASS));
    }

    window.crewdevToggleAside = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        var next = !current();
        apply(next);
        writeLocal(next);

        var ui = window.__crewdevUi || {};
        if (ui.prefsUrl && ui.csrf) {
            fetch(ui.prefsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': ui.csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ sidebar_collapsed: next }),
                credentials: 'same-origin'
            }).catch(function () {});
        }
        return false;
    };

    function boot() {
        var stored = readLocal();
        var collapsed;
        if (stored === '1') collapsed = true;
        else if (stored === '0') collapsed = false;
        else collapsed = !!(window.__crewdevUi && window.__crewdevUi.sidebarCollapsed);
        apply(collapsed);
        writeLocal(collapsed);
    }

    boot();
    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
    document.addEventListener('turbo:render', boot);
})();
</script>
@endauth
