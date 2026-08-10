/* Fallback: основная логика в brand/header (window.crewdevToggleAside). */
(function () {
    if (typeof window.crewdevToggleAside === 'function') {
        return;
    }

    var KEY = 'crewdev.aside.collapsed';
    var CLASS = 'crewdev-aside-collapsed';

    function apply(collapsed) {
        collapsed = !!collapsed;
        document.documentElement.classList.toggle(CLASS, collapsed);
        if (document.body) document.body.classList.toggle(CLASS, collapsed);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest && e.target.closest('#crewdev-aside-toggle, .crewdev-aside-toggle');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var next = !document.body.classList.contains(CLASS);
        apply(next);
        try { localStorage.setItem(KEY, next ? '1' : '0'); } catch (err) {}
    }, true);
})();
