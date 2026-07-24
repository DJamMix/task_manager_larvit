<?php

namespace App\Orchid\Screens\Task;

use App\Models\Task;
use App\Orchid\Filters\TaskCategoryFilter;
use App\Orchid\Filters\TaskExecutorFilter;
use App\Orchid\Filters\TaskProjectFilter;
use App\Orchid\Filters\TaskSearchFilter;
use App\Orchid\Filters\TaskStatusFilter;
use App\Orchid\Layouts\Task\TaskListLayout;
use App\Services\ProjectContext;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class TaskListScreen extends Screen
{
    public function query(ProjectContext $context): iterable
    {
        $query = Task::with(['creator', 'executor', 'project', 'category']);
        $context->applyToTaskQuery($query);

        return [
            'tasks' => $query->filters()
                ->defaultSort('id', 'desc')
                ->paginate(15),
        ];
    }

    public function name(): ?string
    {
        $project = app(ProjectContext::class)->project();

        return $project ? 'Задачи — ' . $project->name : 'Задачи';
    }

    public function description(): ?string
    {
        return app(ProjectContext::class)->has()
            ? 'Список отфильтрован активным проектом из меню слева.'
            : null;
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.tasks',
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make(__('project.add'))
                ->icon('bs.plus-circle')
                ->route('platform.systems.tasks.create'),
        ];
    }

    public function layout(): iterable
    {
        $context = app(ProjectContext::class);

        $filters = [
            TaskSearchFilter::class,
            TaskCategoryFilter::class,
            TaskStatusFilter::class,
            TaskExecutorFilter::class,
        ];

        if (!$context->has()) {
            $filters[] = TaskProjectFilter::class;
        }

        return [
            Layout::view('partials.project-context-banner'),
            Layout::selection($filters),
            TaskListLayout::class,
        ];
    }
}
