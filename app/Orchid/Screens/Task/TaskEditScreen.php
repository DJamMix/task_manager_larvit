<?php

namespace App\Orchid\Screens\Task;

use App\Models\Task;
use App\Orchid\Layouts\Client\ClientTaskFilesLayout;
use App\Orchid\Layouts\Comment\CommentListLayout;
use App\Orchid\Layouts\Comment\CommentSendLayout;
use App\Orchid\Layouts\Task\TaskEditLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class TaskEditScreen extends Screen
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
        return [
            'task' => $task,
            'comments' => $task->comments()
                ->with('user')
                ->latest()
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
            'text' => $comment->plain_text
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->task->exists ? 'Редактировать' : 'Создать';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.tasks',
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
            Button::make(__('project.remove.title'))
                ->icon('bs.trash3')
                ->confirm(__('project.remove.warning'))
                ->method('remove')
                ->canSee($this->task->exists),

            Button::make(__('project.save'))
                ->icon('bs.check-circle')
                ->method('save'),
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
                'Редактирование информации' => [
                    TaskEditLayout::class,
                    ClientTaskFilesLayout::class,
                ],
                'Комментарии' => [
                    CommentSendLayout::class,
                    CommentListLayout::class,
                ],
            ]),
        ];
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request, Task $task)
    {
        $task->fill($request->get('task'));
        $task->attachments()->syncWithoutDetaching(
            $request->input('task.attachments', [])
        );
        $task->save();

        Toast::info(__('task.save'));

        return redirect()->route('platform.systems.tasks');
    }

    /**
     * @throws \Exception
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove(Task $task)
    {
        $task->delete();

        Toast::info(__('task.remove'));

        return redirect()->route('platform.systems.tasks');
    }

    public function addComment(Request $request, Task $task)
    {
        // Получаем данные из Quill редактора
        $quillData = $request->input('comment.text');
        
        // Если данные пришли как массив (обычный случай для Quill)
        if (is_array($quillData)) {
            $quillContent = $quillData;
        } 
        // Если данные пришли как JSON строка (на всякий случай)
        elseif (json_validate($quillData)) {
            $quillContent = json_decode($quillData, true);
        } 
        // Если данные в непонятном формате
        else {
            $quillContent = [
                'ops' => [
                    ['insert' => $quillData]
                ]
            ];
        }

        // Извлекаем plain text из Quill delta
        $plainText = '';
        foreach ($quillContent['ops'] ?? [] as $op) {
            if (is_string($op['insert'] ?? null)) {
                $plainText .= $op['insert'];
            }
        }

        // Удаляем лишние переносы строк
        $plainText = trim(preg_replace('/\s+/', ' ', $plainText));

        $task->comments()->create([
            'user_id' => auth()->id(),
            'text' => $quillContent,
            'plain_text' => $plainText
        ]);

        Toast::success('Комментарий добавлен');
    }
}
