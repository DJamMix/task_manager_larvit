<?php

namespace App\Orchid\Screens\TaskCategory;

use App\Models\TaskCategory;
use App\Orchid\Layouts\TaskCategory\TaskCategoryEditLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class TaskCategoryEditScreen extends Screen
{
    /**
     * @var TaskCategory
     */
    public $task_category;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(TaskCategory $task_category): iterable
    {
        return [
            'task_category' => $task_category,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->task_category->exists ? 'Редактировать' : 'Создать';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.task_categories',
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
                ->canSee($this->task_category->exists),

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
            TaskCategoryEditLayout::class,
        ];
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request, TaskCategory $task_category)
    {
        $request->validate([
            'task_category.name' => 'required|string|max:255',
        ]);

        $task_category->fill($request->get('task_category'));
        $task_category->save();

        Toast::info(__('task_category.save'));

        return redirect()->route('platform.systems.task_categories');
    }

    /**
     * @throws \Exception
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove(TaskCategory $task_category)
    {
        $task_category->delete();

        Toast::info(__('task_category.remove'));

        return redirect()->route('platform.systems.task_categories');
    }
}
