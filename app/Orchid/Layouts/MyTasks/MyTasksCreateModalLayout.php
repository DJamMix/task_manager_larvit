<?php

namespace App\Orchid\Layouts\MyTasks;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskTypeEnum;
use App\Models\Project;
use App\Models\TaskCategory;
use App\Services\ProjectContext;
use App\Support\UploadLimits;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Quill;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Upload;
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
            ->title('Название')
            ->placeholder('Название задачи')
            ->class('tc-field-title');

        $meta = [];

        if ($context->has()) {
            $fields[] = Input::make('task.project_id')
                ->type('hidden')
                ->value($context->id());

            $meta[] = \Orchid\Screen\Fields\Label::make('project_context_info')
                ->title('Проект')
                ->value($context->project()->name);
        } else {
            $meta[] = Select::make('task.project_id')
                ->fromQuery(
                    Project::query()->whereIn('id', $context->availableProjects()->pluck('id')),
                    'name',
                    'id'
                )
                ->title('Проект')
                ->required()
                ->empty('Проект')
                ->class('tc-field-chip');
        }

        $meta[] = Select::make('task.task_category_id')
            ->fromModel(TaskCategory::class, 'name', 'id')
            ->title('Категория')
            ->required()
            ->empty('Категория')
            ->class('tc-field-chip');

        $meta[] = Select::make('task.type_task')
            ->options(TaskTypeEnum::options())
            ->title('Тип')
            ->required()
            ->value(TaskTypeEnum::DEFAULT->value)
            ->class('tc-field-chip');

        $meta[] = Select::make('task.priority')
            ->options(
                collect(TaskPriorityEnum::orderedCases())
                    ->mapWithKeys(fn ($p) => [$p->value => "{$p->code()} {$p->label()}"])
                    ->all()
            )
            ->title('Приоритет')
            ->required()
            ->value(TaskPriorityEnum::MEDIUM->value)
            ->class('tc-field-chip');

        $meta[] = DateTimer::make('task.end_datetime')
            ->title('Дедлайн')
            ->enableTime()
            ->allowInput()
            ->placeholder('Не выбран')
            ->class('tc-field-chip form-control');

        $fields[] = Group::make($meta)->fullWidth()->alignEnd();

        $fields[] = Quill::make('task.description')
            ->toolbar(['text', 'list', 'quote'])
            ->height('160px')
            ->title('Описание')
            ->placeholder('Описание, шаги, критерии. Код — </>')
            ->required();

        $fields[] = Upload::make('task.attachments')
            ->title('Вложения')
            ->acceptedFiles('image/*,application/pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.txt,.psd,.fig')
            ->storage('public')
            ->maxFileSize(UploadLimits::maxMb(50))
            ->maxFiles(8);

        return $fields;
    }
}
