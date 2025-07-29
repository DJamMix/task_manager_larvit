<?php

namespace App\Orchid\Screens\Client;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Project;
use App\Models\Task;
use App\Orchid\Layouts\Client\ClientTaskCreateModalLayout;
use App\Orchid\Layouts\Client\ClientTaskViewLayout;
use App\Orchid\Layouts\Comment\CommentListLayout;
use App\Orchid\Layouts\Comment\CommentSendLayout;
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
        return [
            'user' => auth()->user(),
            'project' => $project,
            'task' => $task,
            'task_status_label' => TaskStatusEnum::from($task->status)?->label(),
            'comments' => $task->comments()
                ->with('user')
                ->oldest()
                ->get()
                ->map(fn($comment) => $this->transformComment($comment)),
        ];
    }

    protected function transformComment($comment): array
    {
        return [
            'id' => $comment->id,
            'user' => ['name' => $comment->user->name ?? 'Неизвестно'],
            'created_at' => $comment->created_at->format('d.m.Y H:i'),
            'text' => $comment->text
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Просмотр задачи';
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

        if($task['status'] === TaskStatusEnum::DRAFT->value) {
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

        if($task['status'] === TaskStatusEnum::ESTIMATION_REVIEW->value) {
            $buttons[] = Button::make('Принять')
                ->method('applyTask')
                ->icon('check')
                ->class('btn btn-success')
                ->confirm('При нажатии "принять", вы соглашаетесь с оценкой и задача переходит исполнителю.');

            $buttons[] = Button::make('Отклонить')
                ->method('cancelTask')
                ->icon('x')
                ->class('btn btn-danger')
                ->confirm('При нажатии "отклонить", задача будет отменена.');
        }

        return $buttons;
    }

    public function approveTask(Task $task)
    {
        $task->status = TaskStatusEnum::ESTIMATION->value;
        $task->save();

        Toast::success('Задача успешно согласована и переведена в статус "Оценки исполнителем"');

        return redirect()->back();
    }

    public function applyTask(Task $task)
    {
        $task->status = TaskStatusEnum::NEW->value;
        $task->save();

        Toast::success('Оценка успешно согласована и переведена в статус "Новая"');

        return redirect()->back();
    }

    public function cancelTask(Task $task)
    {
        $task->status = TaskStatusEnum::CANCELED->value;
        $task->save();

        Toast::success('Оценка отклонена и задача переведена в статус "Отменена"');

        return redirect()->back();
    }

    public function updateTask(Request $request, Task $task, Project $project)
    {
        $validated = $request->validate([
            'task.name' => 'required|string|max:255',
            'task.description' => 'required|string',
            'task.task_category_id' => 'required|exists:task_categories,id',
            'task.type' => 'required|string',
            'task.priority' => 'required|string',
        ]);

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
            Layout::tabs([
                'Основная информация' => ClientTaskViewLayout::class,
                'Обсуждение' => [
                    CommentSendLayout::class,
                    CommentListLayout::class,
                ],
            ]),

            Layout::modal('editTaskModal', [
                ClientTaskCreateModalLayout::class
            ])
                ->title('Редактирование задачи')
                ->applyButton('Сохранить')
                ->closeButton('Отмена'),
        ];
    }

    public function addComment(Request $request, Task $task)
    {
        $request->validate([
            'comment.text' => 'required|string|max:1000',
        ]);

        $task->comments()->create([
            'user_id' => auth()->id(),
            'text' => $request->input('comment.text'),
        ]);

        Toast::success('Комментарий добавлен');
    }
}
