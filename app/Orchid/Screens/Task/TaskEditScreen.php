<?php

namespace App\Orchid\Screens\Task;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Orchid\Layouts\Client\ClientTaskFilesLayout;
use App\Orchid\Layouts\Comment\DiscussionComposerLayout;
use App\Orchid\Layouts\Task\TaskEditLayout;
use App\Orchid\Layouts\Task\TaskObserversLayout;
use App\Services\CommentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class TaskEditScreen extends Screen
{
    /**
     * @var Task
     */
    public $task;

    public function query(Task $task): iterable
    {
        if (!$task->exists) {
            $context = app(\App\Services\ProjectContext::class);
            if ($context->has()) {
                $task->project_id = $context->id();
            }
        }

        if ($task->exists) {
            $task->load(['project', 'executor', 'creator', 'category', 'attachment']);
        }

        $user = auth()->user();

        return [
            'task' => $task,
            'task_status_label' => $task->exists
                ? TaskStatusEnum::tryFrom($task->status)?->label()
                : null,
            'discussion_comments' => $task->exists
                ? $task->comments()
                    ->with(['user', 'parent.user', 'attachment'])
                    ->orderBy('created_at')
                    ->get()
                : collect(),
            'notify_options' => $task->exists ? $task->participantsForNotify() : [],
            'can_discuss' => $task->exists && $task->canDiscuss((int) $user->id),
            'is_observer_only' => false,
            'viewer_role' => 'admin',
            'show_time_link' => false,
            'time_route' => null,
            'timeEntries' => $task->exists
                ? $task->timeEntries()->with('user')->latest()->get()
                : collect(),
        ];
    }

    public function name(): ?string
    {
        return $this->task->exists ? ($this->task->name ?? 'Редактировать') : 'Создать задачу';
    }

    public function description(): ?string
    {
        return $this->task->exists
            ? 'Карточка задачи, обсуждение и наблюдатели'
            : 'Создание новой задачи';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.tasks',
        ];
    }

    public function commandBar(): iterable
    {
        $buttons = [];

        if ($this->task->exists) {
            $buttons[] = Link::make('К списку')
                ->icon('bs.arrow-left')
                ->route('platform.systems.tasks');
        }

        $buttons[] = Button::make(__('project.remove.title'))
            ->icon('bs.trash3')
            ->confirm(__('project.remove.warning'))
            ->method('remove')
            ->canSee($this->task->exists);

        $buttons[] = Button::make(__('project.save'))
            ->icon('bs.check-circle')
            ->method('save');

        return $buttons;
    }

    public function layout(): iterable
    {
        if (!$this->task->exists) {
            return [
                TaskEditLayout::class,
                TaskObserversLayout::class,
                ClientTaskFilesLayout::class,
            ];
        }

        return [
            Layout::tabs([
                'Задача и обсуждение' => [
                    Layout::view('orchid.layouts.task-workspace'),
                    Layout::view('orchid.layouts.composer-anchor'),
                    DiscussionComposerLayout::class,
                ],
                'Редактирование' => [
                    TaskEditLayout::class,
                    TaskObserversLayout::class,
                    ClientTaskFilesLayout::class,
                ],
                'Учёт времени' => [
                    Layout::table('timeEntries', [
                        TD::make('work_date', 'Дата')
                            ->render(fn ($entry) => $entry->work_date->format('d.m.Y')),
                        TD::make('user.name', 'Исполнитель')
                            ->render(fn ($entry) => $entry->user?->displayName() ?? '—'),
                        TD::make('hours_spent', 'Часы')
                            ->alignRight()
                            ->render(fn ($entry) => number_format($entry->hours_spent, 2)),
                        TD::make('work_description', 'Описание')
                            ->render(fn ($entry) => Str::limit($entry->work_description, 100)),
                    ]),
                ],
            ]),
        ];
    }

    public function save(Request $request, Task $task)
    {
        $data = $request->get('task');

        if (!is_array($data) || $data === []) {
            Toast::warning('Откройте вкладку «Редактирование», чтобы сохранить карточку задачи');
            return back();
        }

        if (!$task->exists && empty($data['project_id'])) {
            $contextId = app(\App\Services\ProjectContext::class)->id();
            if ($contextId) {
                $data['project_id'] = $contextId;
            }
        }

        if (array_key_exists('observers_ids', $data)) {
            $data['observers_ids'] = collect($data['observers_ids'] ?? [])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $task->fill($data);
        $task->save();

        $task->attachments()->syncWithoutDetaching(
            $request->input('task.attachments', [])
        );

        Toast::info(__('task.save'));

        return redirect()->route('platform.systems.tasks.edit', $task);
    }

    public function remove(Task $task)
    {
        $task->delete();

        Toast::info(__('task.remove'));

        return redirect()->route('platform.systems.tasks');
    }

    public function addComment(Request $request, Task $task, CommentService $comments)
    {
        if (!$task->canDiscuss()) {
            Toast::error('Нельзя писать в этой задаче');
            return back();
        }

        $comments->addFromRequest($task, $request->user(), $request);
        Toast::success('Сообщение отправлено');

        return redirect()->route('platform.systems.tasks.edit', $task);
    }
}
