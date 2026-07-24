<div id="task-composer-anchor" class="tw-composer-wrap">
    <div id="reply-banner" class="tw-reply-banner d-none">
        <div>
            Ответ для <strong id="reply-author"></strong>
            <div class="small text-muted">Автор получит уведомление в колокольчик</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="reply-cancel">Отмена</button>
    </div>
</div>

<script>
(() => {
    const parentInput = () => document.getElementById('comment-parent-id');
    const banner = () => document.getElementById('reply-banner');
    const authorEl = () => document.getElementById('reply-author');

    document.addEventListener('click', (event) => {
        const replyBtn = event.target.closest?.('.tw-reply-btn');
        if (replyBtn) {
            const id = replyBtn.getAttribute('data-parent-id');
            const author = replyBtn.getAttribute('data-author') || 'участник';
            const input = parentInput();
            if (input) input.value = id;
            banner()?.classList.remove('d-none');
            const el = authorEl();
            if (el) el.textContent = author;
            document.getElementById('task-composer-anchor')
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => document.querySelector('.ql-editor')?.focus(), 200);
            return;
        }

        const copyBtn = event.target.closest?.('.tw-code-copy');
        if (!copyBtn) return;

        const block = copyBtn.closest('.tw-codeblock');
        const code = block?.querySelector('code')?.innerText ?? '';
        if (!code) return;

        const done = () => {
            const prev = copyBtn.textContent;
            copyBtn.textContent = 'Скопировано';
            setTimeout(() => { copyBtn.textContent = prev || 'Копировать'; }, 1500);
        };

        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(code).then(done).catch(() => {});
        } else {
            const ta = document.createElement('textarea');
            ta.value = code;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
            done();
        }
    });

    document.getElementById('reply-cancel')?.addEventListener('click', () => {
        const input = parentInput();
        if (input) input.value = '';
        banner()?.classList.add('d-none');
    });

    const feed = document.getElementById('task-discussion-feed');
    if (feed) feed.scrollTop = feed.scrollHeight;
})();
</script>
