<?php

namespace App\Orchid\Layouts\MyTasks;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskTypeEnum;
use App\Models\Project;
use App\Models\TaskCategory;
use App\Services\ProjectContext;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Quill;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class MyTasksCreateModalLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        $context = app(ProjectContext::class);
        $fields = [];

        $fields[] = Input::make('task.name')
            ->type('text')
            ->max(255)
            ->required()
            ->title(__('task.name'))
            ->placeholder(__('task.name'));

        if ($context->has()) {
            $fields[] = Label::make('project_context_info')
                ->title('Проект')
                ->value($context->project()->name)
                ->help('Будет создана в активном проекте. Сменить проект можно в меню слева.');

            $fields[] = Input::make('task.project_id')
                ->type('hidden')
                ->value($context->id());
        } else {
            $fields[] = Select::make('task.project_id')
                ->fromQuery(
                    Project::query()->whereIn('id', $context->availableProjects()->pluck('id')),
                    'name',
                    'id'
                )
                ->title(__('task.project_id'))
                ->required()
                ->help('Или выберите проект в меню слева — тогда поле не понадобится');
        }

        $fields[] = Select::make('task.task_category_id')
            ->fromModel(TaskCategory::class, 'name', 'id')
            ->title(__('task.task_category_id'))
            ->required();

        $fields[] = Select::make('task.type_task')
            ->options(TaskTypeEnum::options())
            ->title('Тип задачи')
            ->required()
            ->help('Выберите тип задачи')
            ->value(TaskTypeEnum::DEFAULT->value);

        $fields[] = Label::make('priority_help')
            ->title('Описание приоритетов:')
            ->help(collect(TaskPriorityEnum::orderedCases())
                ->map(fn ($p) => "<b>{$p->label()}:</b> {$p->description()}")
                ->join('<br>'));

        $fields[] = Select::make('task.priority')
            ->options(TaskPriorityEnum::options())
            ->title('Приоритет задачи')
            ->required()
            ->help('Выберите приоритет выполнения задачи')
            ->value(TaskPriorityEnum::MEDIUM->value);

        $fields[] = DateTimer::make('task.end_datetime')
            ->title('Дедлайн')
            ->enableTime()
            ->allowInput()
            ->help('Необязательно. Помогает не пропускать сроки.');

        $fields[] = Quill::make('task.description')->toolbar(["text", "color", "header", "list", "format"])
            ->title(__('task.description'))
            ->required();

        return $fields;
    }
}
