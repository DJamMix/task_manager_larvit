<div class="tc-modal">
    <div class="tc-modal__intro">Свойства задачи · после создания уйдёт на согласование</div>
    {!! $fields ?? '' !!}
</div>

<script>
(() => {
    // Tom Select: выпадающий список в body — не обрезается модалкой
    const patchTomSelect = () => {
        document.querySelectorAll('.tc-modal .ts-wrapper, .modal .ts-wrapper').forEach((wrap) => {
            if (wrap.dataset.tcPatched === '1') return;
            const select = wrap.querySelector('select');
            const tom = select?.tomselect;
            if (!tom) return;

            wrap.dataset.tcPatched = '1';
            try {
                if (typeof tom.settings === 'object') {
                    tom.settings.dropdownParent = 'body';
                }
                const open = tom.open.bind(tom);
                tom.open = function patchedOpen() {
                    if (this.dropdown && this.dropdown.parentNode !== document.body) {
                        document.body.appendChild(this.dropdown);
                    }
                    this.dropdown?.classList.add('tc-ts-dropdown');
                    // Позиционирование под контролом
                    try {
                        const rect = this.control.getBoundingClientRect();
                        Object.assign(this.dropdown.style, {
                            position: 'fixed',
                            top: (rect.bottom + 4) + 'px',
                            left: rect.left + 'px',
                            width: Math.max(rect.width, 220) + 'px',
                            zIndex: '2000',
                        });
                    } catch (err) {}
                    return open();
                };
            } catch (e) {
                // ignore
            }
        });
    };

    const obs = new MutationObserver(() => patchTomSelect());
    obs.observe(document.body, { childList: true, subtree: true });
    setTimeout(patchTomSelect, 300);
    setTimeout(patchTomSelect, 900);
    document.addEventListener('shown.bs.modal', () => setTimeout(patchTomSelect, 50));
    document.addEventListener('orchid:screen', () => setTimeout(patchTomSelect, 50));
})();
</script>
