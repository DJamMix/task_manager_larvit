<div class="tc-modal">
    <div class="tc-modal__intro">Свойства задачи · после создания уйдёт на согласование</div>
    {!! $fields ?? '' !!}
</div>

<script>
(() => {
    const prepareCreateTaskForm = (root) => {
        const modal = root?.id === 'screen-modal-createTaskModal'
            ? root
            : document.getElementById('screen-modal-createTaskModal');
        if (!modal) {
            return;
        }

        const form = modal.querySelector('form');
        if (!form) {
            return;
        }

        form.setAttribute('novalidate', 'novalidate');
        form.setAttribute('data-form-submit-value', 'false');
        form.querySelectorAll('button[type="submit"]').forEach((btn) => {
            btn.disabled = false;
            btn.classList.remove('cursor-wait', 'btn-loading');
            btn.querySelectorAll('.spinner-loading').forEach((el) => el.remove());
            btn.setAttribute('formnovalidate', 'true');
        });
        form.querySelectorAll('[data-controller="quill"] textarea').forEach((ta) => {
            ta.removeAttribute('required');
            const editor = ta.closest('[data-controller="quill"]')?.querySelector('.ql-editor');
            if (editor) {
                ta.value = editor.innerHTML || '';
            }
        });
    };

    const patchTomSelect = (root) => {
        (root || document).querySelectorAll('.tc-modal .ts-wrapper, #screen-modal-createTaskModal .ts-wrapper').forEach((wrap) => {
            if (wrap.dataset.tcPatched === '1') {
                return;
            }

            const select = wrap.querySelector('select');
            const tom = select?.tomselect;
            if (!tom) {
                return;
            }

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
            } catch (e) {}
        });
    };

    if (!window.__tcCreateTaskBound) {
        window.__tcCreateTaskBound = true;

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            if (!form.closest('#screen-modal-createTaskModal') && !form.querySelector('.tc-modal')) {
                return;
            }
            prepareCreateTaskForm(form.closest('.modal'));
        }, true);

        document.addEventListener('shown.bs.modal', (event) => {
            if (event.target?.id !== 'screen-modal-createTaskModal') {
                return;
            }
            prepareCreateTaskForm(event.target);
            setTimeout(() => patchTomSelect(event.target), 50);
        });
    }

    prepareCreateTaskForm();
    setTimeout(() => patchTomSelect(), 300);
})();
</script>
