<div class="crewdev-aside-brand-wrap">
    <a href="{{ route('platform.index') }}" class="crewdev-aside-brand" title="{{ config('app.name') }}">
        {{ config('app.name') }}
    </a>
    <button type="button"
            class="crewdev-aside-toggle"
            id="crewdev-aside-toggle"
            title="Свернуть меню"
            aria-label="Свернуть меню"
            aria-pressed="false"
            onclick="window.crewdevToggleAside && window.crewdevToggleAside(event)">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
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

    function syncLinkTitles(collapsed) {
        document.querySelectorAll('.aside a.nav-link').forEach(function (link) {
            if (collapsed) {
                if (!link.dataset.crewdevTitleSaved) {
                    link.dataset.crewdevTitleSaved = '1';
                    link.dataset.crewdevTitleOrig = link.getAttribute('title') || '';
                }
                var label = (link.textContent || '').replace(/\s+/g, ' ').trim();
                if (label) link.setAttribute('title', label);
            } else if (link.dataset.crewdevTitleSaved) {
                var orig = link.dataset.crewdevTitleOrig || '';
                if (orig) link.setAttribute('title', orig);
                else link.removeAttribute('title');
                delete link.dataset.crewdevTitleSaved;
                delete link.dataset.crewdevTitleOrig;
            }
        });
    }

    function apply(collapsed) {
        collapsed = !!collapsed;
        document.documentElement.classList.toggle(CLASS, collapsed);
        if (document.body) document.body.classList.toggle(CLASS, collapsed);

        var btn = document.getElementById('crewdev-aside-toggle');
        if (btn) {
            btn.title = collapsed ? 'Развернуть меню' : 'Свернуть меню';
            btn.setAttribute('aria-label', btn.title);
            btn.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        }
        syncLinkTitles(collapsed);
    }

    function current() {
        return document.documentElement.classList.contains(CLASS)
            || !!(document.body && document.body.classList.contains(CLASS));
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
    }

    boot();
    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
    document.addEventListener('turbo:render', boot);
})();
</script>
@endauth
