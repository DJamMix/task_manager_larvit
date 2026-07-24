<?php

namespace App\Orchid\Screens\Client;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Project;
use App\Models\Task;
use App\Orchid\Layouts\Client\ClientTaskCreateModalLayout;
use App\Orchid\Layouts\Comment\DiscussionComposerLayout;
use App\Services\CommentService;
use App\Services\TaskLogger;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ClientViewTaskScreen extends Screen
{
    /**
     * @var Task
     */
    public $task;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Project $project, Task $task): iterable
    {
        $user = auth()->user();

        if (
            !$user->hasAccess('platform.systems.tasks')
            && !$user->projects()->where('projects.id', $project->id)->exists()
        ) {
            abort(403);
        }

        $task->load(['project', 'executor', 'creator', 'category', 'attachment']);

        return [
            'user' => $user,
            'project' => $project,
            'task' => $task,
            'task_status_label' => TaskStatusEnum::tryFrom($task->status)?->label(),
            'discussion_comments' => $task->comments()
                ->with(['user', 'parent.user', 'attachment'])
                ->orderBy('created_at')
                ->get(),
            'notify_options' => $task->participantsForNotify(),
            'can_discuss' => true,
            'is_observer_only' => false,
            'viewer_role' => 'client',
            'show_time_link' => false,
            'time_route' => null,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->task->name ?? 'Просмотр задачи';
    }

    public function description(): ?string
    {
        return 'Описание задачи и обсуждение с исполнителем';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        $task = $this->task;

        $buttons = [];

        if ($task->status === TaskStatusEnum::DRAFT->value) {
            $buttons[] = Button::make('Согласовано')
                ->method('approveTask')
                ->icon('check')
                ->class('btn btn-success')
                ->confirm('Вы уверены, что хотите согласовать эту задачу?');

            $buttons[] = ModalToggle::make('Редактировать')
                ->modal('editTaskModal')
                ->method('updateTask')
                ->icon('pencil')
                ->class('btn btn-primary');
        }

        if ($task->status === TaskStatusEnum::ESTIMATION_REVIEW->value) {
            $buttons[] = Button::make('Принять')
                ->method('applyTask')
                ->icon('check')
                ->class('btn btn-success')
                ->confirm('При нажатии "принять", вы соглашаетесь с оценкой и задача переходит исполнителю.');

            $buttons[] = ModalToggle::make('Отклонить оценку')
                ->modal('returnTaskModal')
                ->method('returnTask')
                ->icon('arrow-return-right')
                ->class('btn btn-warning')
                ->confirm('При нажатии вы не принимаете оценку, задача возвращается на оценивание, после чего исполнитель либо переоценивает её, либо даёт пояснение почему задача займёт столько времени.');

            $buttons[] = ModalToggle::make('Отменить задачу')
                ->modal('cancelTaskModal')
                ->method('cancelTask')
                ->icon('x')
                ->class('btn btn-danger')
                ->confirm('При нажатии задача будет переведена в статус "Не оплачена" в связи с тем, что исполнитель затратил время на оценивание реализации.');
        }

        if ($task->status === TaskStatusEnum::DEMO->value) {
            $buttons[] = Button::make('Принять демо')
                ->method('applyDemoTask')
                ->icon('check')
                ->class('btn btn-success')
                ->confirm('При нажатии "принять", вы соглашаетесь с выполненой работой и задача считается выполненной.');

            $buttons[] = Button::make('Вернуть на доработку')
                ->method('returnDemoModal')
                ->icon('arrow-return-right')
                ->class('btn btn-warning')
                ->confirm('При нажатии вы не принимаете задачу, задача возвращается на доработку!');
        }

        $buttons[] = Link::make('К проекту')
            ->icon('bs.arrow-left')
            ->route('platform.systems.client.project.tasks', $task->project_id);

        return $buttons;
    }

    public function approveTask(Task $task)
    {
        $task->status = TaskStatusEnum::ESTIMATION->value;
        $task->save();

        app(TaskLogger::class)->logCustomAction(
            $task, 
            auth()->user(),
            'Согласовал задачу'
        );

        Toast::success('Задача успешно согласована и переведена в статус "Оценки исполнителем"');

        return redirect()->back();
    }

    public function applyTask(Task $task)
    {
        $task->status = TaskStatusEnum::NEW->value;
        $task->save();

        app(TaskLogger::class)->logCustomAction(
            $task, 
            auth()->user(),
            'Согласовал оценку исполнителя'
        );

        Toast::success('Оценка успешно согласована и переведена в статус "Новая"');

        return redirect()->back();
    }

    public function applyDemoTask(Task $task)
    {
        $task->status = TaskStatusEnum::UNPAID->value;
        $task->save();

        app(TaskLogger::class)->logStatusChange(
            $task, 
            auth()->user(),
            $task->status
        );

        Toast::success('Вы приняли задачу и она считается выполненной!');

        return redirect()->back();
    }

    public function cancelTask(Task $task,  Request $request)
    {
        $request->validate([
            'cancel_reason' => 'required|string|min:10|max:1000',
        ]);

        $task->status = TaskStatusEnum::UNPAID->value;
        $task->save();

        app(TaskLogger::class)->logTaskCancellation(
            $task, 
            auth()->user(), 
            $request->input('cancel_reason')
        );

        Toast::success('Задача отменена. Причина: ' . $request->input('cancel_reason'));

        return redirect()->back();
    }

    public function returnTask(Task $task,  Request $request)
    {
        $request->validate([
            'return_reason' => 'required|string|min:10|max:1000',
        ]);

        $task->status = TaskStatusEnum::ESTIMATION->value;
        $task->save();

        app(TaskLogger::class)->logTaskReturnEstimation(
            $task, 
            auth()->user(), 
            $request->input('return_reason')
        );

        Toast::success('Задача возвращена на оценку. Причина: ' . $request->input('return_reason'));

        return redirect()->back();
    }

    public function returnDemoModal(Task $task,  Request $request)
    {
        $task->status = TaskStatusEnum::IN_PROGRESS->value;
        $task->save();

        app(TaskLogger::class)->logTaskReturnDemoEstimation(
            $task, 
            auth()->user()
        );

        Toast::success('Задача возвращена исполнителю после результатов ДЕМО!');

        return redirect()->back();
    }

    public function updateTask(Request $request, Task $task, Project $project)
    {
        $validated = $request->validate([
            'task.name' => 'required|string|max:255',
            'task.description' => 'required|string',
            'task.task_category_id' => 'required|exists:task_categories,id',
            'task.type_task' => 'required|string',
            'task.priority' => 'required|string',
        ]);

        $task->attachments()->syncWithoutDetaching(
            $request->input('task.attachments', [])
        );

        $task->fill($validated['task']);
        $task->save();

        Toast::success('Задача успешно обновлена');

        return redirect()->back();
    }

    public function back()
    {
        return redirect()->route('platform.systems.client.projects');
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.client.project.tasks.view',
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::view('orchid.layouts.task-workspace'),
            Layout::view('orchid.layouts.composer-anchor'),
            DiscussionComposerLayout::class,

            Layout::modal('editTaskModal', [
                ClientTaskCreateModalLayout::class,
            ])
                ->title('Редактирование задачи')
                ->applyButton('Сохранить')
                ->closeButton('Отмена'),

            Layout::modal('cancelTaskModal', [
                Layout::rows([
                    \Orchid\Screen\Fields\TextArea::make('cancel_reason')
                        ->title('Причина отмены')
                        ->required()
                        ->help('Пожалуйста, укажите подробную причину отмены задачи'),
                ]),
            ])
                ->title('Отмена задачи')
                ->applyButton('Подтвердить отмену')
                ->closeButton('Отмена'),

            Layout::modal('returnTaskModal', [
                Layout::rows([
                    \Orchid\Screen\Fields\TextArea::make('return_reason')
                        ->title('Причина возврата на оценку?')
                        ->required()
                        ->help('Пожалуйста, укажите подробную причину возврата на оценку задачи'),
                ]),
            ])
                ->title('Отклонение оценки')
                ->applyButton('Подтвердить отклонение оценки')
                ->closeButton('Отмена'),
        ];
    }

    public function addComment(Request $request, Task $task, CommentService $comments)
    {
        $comments->addFromRequest($task, $request->user(), $request);
        Toast::success('Сообщение отправлено. Участники получат уведомление.');

        return back();
    }
}
