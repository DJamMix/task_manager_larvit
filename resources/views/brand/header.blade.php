<a href="{{ route('platform.index') }}" class="crewdev-aside-brand" title="CrewDev">
    <span class="crewdev-aside-brand__mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 8V16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M8 12H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </span>
    <span class="crewdev-aside-brand__text">CrewDev</span>
</a>
<button type="button"
        class="crewdev-aside-toggle"
        id="crewdev-aside-toggle"
        title="Свернуть меню"
        aria-label="Свернуть меню">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</button>
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
window.__crewdevUi = {
    sidebarCollapsed: @json($sidebarCollapsed),
    prefsUrl: @json(route('platform.ui.preferences')),
    csrf: @json(csrf_token())
};
(function () {
    try {
        var collapsed = window.__crewdevUi.sidebarCollapsed;
        if (localStorage.getItem('crewdev.aside.collapsed') === '1') collapsed = true;
        if (localStorage.getItem('crewdev.aside.collapsed') === '0') collapsed = false;
        var apply = function () {
            if (!document.body) { requestAnimationFrame(apply); return; }
            document.body.classList.toggle('crewdev-aside-collapsed', !!collapsed);
        };
        apply();
    } catch (e) {}
})();
</script>
@endauth
