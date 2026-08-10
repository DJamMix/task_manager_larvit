<article class="yt-sprint-task" draggable="true" data-task-id="{{ $task->id }}">
    <a href="{{ route('platform.systems.tasks.edit', $task) }}" class="yt-sprint-task__key">{{ $task->displayKey() }}</a>
    <a href="{{ route('platform.systems.tasks.edit', $task) }}" class="yt-sprint-task__name">{{ $task->name }}</a>
    <span class="yt-sprint-task__status" style="--st:{{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
    @if($task->executor)
        <span class="yt-sprint-task__user">{{ $task->executor->displayName() }}</span>
    @endif
</article>
