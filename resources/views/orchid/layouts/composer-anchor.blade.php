<div id="task-composer-anchor" class="tw-composer-wrap">
    <div id="reply-banner" class="tw-reply-banner d-none">
        <div>
            Ответ для <strong id="reply-author"></strong>
            <div class="small text-muted">Автор получит уведомление в Telegram</div>
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
        const btn = event.target.closest?.('.tw-reply-btn');
        if (!btn) return;

        const id = btn.getAttribute('data-parent-id');
        const author = btn.getAttribute('data-author') || 'участник';
        const input = parentInput();
        if (input) input.value = id;
        banner()?.classList.remove('d-none');
        const el = authorEl();
        if (el) el.textContent = author;
        document.getElementById('task-composer-anchor')
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => document.querySelector('.ql-editor')?.focus(), 200);
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
