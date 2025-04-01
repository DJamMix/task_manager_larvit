<?php

namespace App\Orchid\Screens\MyTasks;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Orchid\Layouts\Comment\CommentListLayout;
use App\Orchid\Layouts\Comment\CommentSendLayout;
use App\Orchid\Layouts\MyTasks\MyTasksViewLayout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class MyTasksViewScreen extends Screen
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
    public function query(Task $task): iterable
    {
        $status = strtolower($task->status);
        $statusLabel = TaskStatusEnum::tryFrom($task->status)?->label();

        return [
            'task' => [
                'name' => $task->name,
                'creator' => [
                    'name' => $task->creator->name ?? 'Не указан'
                ],
                'project' => [
                    'name' => $task->project->name ?? null
                ],
                'task_category' => [
                    'name' => $task->category->name ?? null
                ],
                'status' => $task->status,
                'status_html' => view('components.task-status', [
                    'status' => $status,
                    'label' => $statusLabel
                ])->render(),
                'status_label' => TaskStatusEnum::tryFrom($task->status)?->label(),
                'pay_status' => $task->pay_status,
                'start_datetime' => $task->start_datetime 
                    ? Carbon::parse($task->start_datetime)->format('d.m.Y H:i') 
                    : 'Не указано',
                'end_datetime' => $task->end_datetime 
                    ? Carbon::parse($task->end_datetime)->format('d.m.Y H:i') 
                    : 'Не указано',
                'hours_spent' => $task->hours_spent ? number_format($task->hours_spent, 2) : '0',
                'description' => $task->description
            ],
            'comments' => $task->comments()
                ->with('user')
                ->oldest()
                ->get()
                ->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'user' => ['name' => $comment->user->name ?? 'Неизвестно'],
                        'created_at' => $comment->created_at->format('d.m.Y H:i'),
                        'text' => $comment->text
                    ];
                })
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return __('adminpanel.MyTasksView');
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.my_tasks',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Назад')
                ->icon('arrow-left')
                ->method('back'),
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
            MyTasksViewLayout::class,
            CommentSendLayout::class,
            CommentListLayout::class,
        ];
    }

    public function back()
    {
        return redirect()->route('platform.systems.my_tasks');
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
        return back();
    }
}
