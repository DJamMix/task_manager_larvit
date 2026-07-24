<?php

namespace App\Orchid\Layouts\MyTasks;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskTypeEnum;
use App\Models\Project;
use App\Models\TaskCategory;
use App\Services\ProjectContext;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
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

        $fields[] = Label::make('create_hint')
            ->value('Заполните суть и параметры. После создания задача уйдёт на согласование.');

        $fields[] = Input::make('task.name')
            ->type('text')
            ->max(255)
            ->required()
            ->title('Название')
            ->placeholder('Кратко: что нужно сделать');

        if ($context->has()) {
            $fields[] = Label::make('project_context_info')
                ->title('Проект')
                ->value($context->project()->name)
                ->help('Активный проект из переключателя слева');

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
                ->title('Проект')
                ->required()
                ->empty('Выберите проект')
                ->help('Или зафиксируйте проект в переключателе слева');
        }

        $fields[] = Group::make([
            Select::make('task.task_category_id')
                ->fromModel(TaskCategory::class, 'name', 'id')
                ->title('Категория')
                ->required()
                ->empty('Выберите'),

            Select::make('task.type_task')
                ->options(TaskTypeEnum::options())
                ->title('Тип')
                ->required()
                ->value(TaskTypeEnum::DEFAULT->value),
        ])->fullWidth();

        $fields[] = Group::make([
            Select::make('task.priority')
                ->options(
                    collect(TaskPriorityEnum::orderedCases())
                        ->mapWithKeys(fn ($p) => [$p->value => "{$p->code()} · {$p->label()}"])
                        ->all()
                )
                ->title('Приоритет')
                ->required()
                ->value(TaskPriorityEnum::MEDIUM->value)
                ->help('P0 — сейчас, P3 — обычная очередь'),

            DateTimer::make('task.end_datetime')
                ->title('Дедлайн')
                ->enableTime()
                ->allowInput()
                ->help('Необязательно'),
        ])->fullWidth();

        $fields[] = Quill::make('task.description')
            ->toolbar(['text', 'list', 'quote', 'format'])
            ->height('220px')
            ->title('Описание')
            ->placeholder('Контекст, шаги, ожидаемый результат. Код — кнопкой </>')
            ->required();

        $fields[] = Upload::make('task.attachments')
            ->title('Файлы')
            ->acceptedFiles('image/*,application/pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.txt,.psd,.fig')
            ->storage('public')
            ->maxFileSize(50)
            ->maxFiles(10)
            ->help('Скриншоты, макеты, документы — до 50 МБ');

        return $fields;
    }
}
