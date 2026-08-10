<div class="yt-board" id="yt-board"
     data-move-url="{{ $move_url }}"
     data-csrf="{{ $csrf }}">
    <div class="yt-board__toolbar">
        <div class="yt-board__board-switch">
            @foreach($boards as $b)
                <a href="{{ route('platform.systems.boards', ['board' => $b->id] + (request('sprint') ? ['sprint' => request('sprint')] : [])) }}"
                   class="yt-chip {{ ($board && (int)$board->id === (int)$b->id) ? 'is-active' : '' }}">
                    {{ $b->name }}
                </a>
            @endforeach
        </div>
        <div class="yt-board__filters">
            <a href="{{ route('platform.systems.boards', array_filter(['board' => $board?->id])) }}"
               class="yt-chip {{ !$active_sprint ? 'is-active' : '' }}">Все задачи</a>
            @foreach($sprints as $sp)
                <a href="{{ route('platform.systems.boards', ['board' => $board?->id, 'sprint' => $sp->id]) }}"
                   class="yt-chip {{ $active_sprint && (int)$active_sprint->id === (int)$sp->id ? 'is-active' : '' }}">
                    {{ $sp->name }}
                    <span class="yt-chip__meta">{{ $sp->statusLabel() }}</span>
                </a>
            @endforeach
        </div>
    </div>

    @if(!$board)
        <div class="yt-empty">Создайте доску через кнопку «Создать доску».</div>
    @else
        <div class="yt-board__cols" id="yt-cols">
            @foreach($columns as $col)
                <section class="yt-col" data-status-id="{{ $col['status_id'] }}" data-column-id="{{ $col['id'] }}">
                    <header class="yt-col__head">
                        <span class="yt-col__dot" style="background:{{ $col['color'] }}"></span>
                        <span class="yt-col__title">{{ $col['name'] }}</span>
                        <span class="yt-col__count">{{ count($col['tasks']) }}</span>
                        @if($col['wip_limit'])
                            <span class="yt-col__wip" title="WIP limit">/ {{ $col['wip_limit'] }}</span>
                        @endif
                    </header>
                    <div class="yt-col__list" data-dropzone="1">
                        @foreach($col['tasks'] as $card)
                            <article class="yt-card"
                                     draggable="true"
                                     data-task-id="{{ $card['id'] }}"
                                     data-status-id="{{ $card['status_id'] }}">
                                <a class="yt-card__key" href="{{ $card['url'] }}">{{ $card['key'] }}</a>
                                <a class="yt-card__title" href="{{ $card['url'] }}">{{ $card['name'] }}</a>
                                <div class="yt-card__foot">
                                    @if($card['priority'])
                                        <span class="yt-card__prio yt-card__prio--{{ $card['priority'] }}">{{ $card['priority'] }}</span>
                                    @endif
                                    @if($card['executor'])
                                        <span class="yt-card__assignee" title="{{ $card['executor'] }}">
                                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($card['executor'], 0, 1)) }}
                                        </span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>

<script>
(function () {
    const root = document.getElementById('yt-board');
    if (!root) return;
    const moveUrl = root.dataset.moveUrl;
    const csrf = root.dataset.csrf;
    let dragCard = null;

    root.querySelectorAll('.yt-card').forEach(card => {
        card.addEventListener('dragstart', (e) => {
            dragCard = card;
            card.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.dataset.taskId);
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            root.querySelectorAll('.yt-col__list').forEach(z => z.classList.remove('is-over'));
            dragCard = null;
        });
    });

    root.querySelectorAll('.yt-col__list').forEach(zone => {
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('is-over');
            e.dataTransfer.dropEffect = 'move';
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('is-over'));
        zone.addEventListener('drop', async (e) => {
            e.preventDefault();
            zone.classList.remove('is-over');
            if (!dragCard) return;
            const col = zone.closest('.yt-col');
            const statusId = col.dataset.statusId;
            const taskId = dragCard.dataset.taskId;
            const siblings = [...zone.querySelectorAll('.yt-card')];
            const after = siblings.find(el => {
                const box = el.getBoundingClientRect();
                return e.clientY < box.top + box.height / 2;
            });
            if (after) zone.insertBefore(dragCard, after);
            else zone.appendChild(dragCard);

            const order = [...zone.querySelectorAll('.yt-card')].map(el => el.dataset.taskId);
            try {
                const res = await fetch(moveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        task_id: Number(taskId),
                        status_id: Number(statusId),
                        order: order.map(Number),
                    }),
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || (data.errors && Object.values(data.errors).flat().join('\n')) || 'Не удалось переместить');
                    location.reload();
                } else {
                    dragCard.dataset.statusId = statusId;
                    col.querySelector('.yt-col__count').textContent = zone.querySelectorAll('.yt-card').length;
                    root.querySelectorAll('.yt-col').forEach(c => {
                        c.querySelector('.yt-col__count').textContent = c.querySelectorAll('.yt-card').length;
                    });
                }
            } catch (err) {
                alert('Ошибка сети');
                location.reload();
            }
        });
    });
})();
</script>
