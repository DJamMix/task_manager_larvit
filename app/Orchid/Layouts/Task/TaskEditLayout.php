<?php

namespace App\Orchid\Layouts\Task;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\CoreLayer\Enums\TaskTypeEnum;
use App\Models\Project;
use App\Models\TaskCategory;
use App\Models\TaskQueue;
use App\Models\User;
use App\Support\UploadLimits;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Quill;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Fields\Upload;
use Orchid\Screen\Layouts\Rows;

class TaskEditLayout extends Rows
{
    /**
     * Used to create the title of a group of form elements.
     *
     * @var string|null
     */
    protected $title;

    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    protected function fields(): iterable
    {
        $taskStatus = $this->query->get('task.status');
        $context = app(\App\Services\ProjectContext::class);
        $task = $this->query->get('task');
        $isNew = !($task?->exists ?? false);

        $projectField = Select::make('task.project_id')
            ->fromModel(Project::class, 'name', 'id')
            ->title(__('task.project_id'))
            ->required()
            ->width('50%');

        if ($isNew && $context->has()) {
            $projectField
                ->value($context->id())
                ->help('Подставлено из активного проекта. Можно сменить.');
        }

        return [
            Group::make([
                Input::make('task.name')
                    ->type('text')
                    ->max(255)
                    ->required()
                    ->title(__('task.name'))
                    ->placeholder(__('task.name')),
            ])->fullWidth(),

            Group::make([
                Select::make('task.executor_id')
                    ->options(User::optionsForSelect())
                    ->title(__('task.executor_id'))
                    ->empty('Не выбран')
                    ->width('50%'),

                $projectField,
            ])->fullWidth(),

            Group::make([
                Select::make('task.queue_id')
                    ->options(TaskQueue::optionsForSelect())
                    ->title('Очередь')
                    ->help($isNew
                        ? 'Ключ задачи: PHP-12, FRONTEND-5… Выбирается только при создании.'
                        : 'Очередь нельзя сменить после создания')
                    ->required($isNew)
                    ->empty('Выберите очередь')
                    ->disabled(!$isNew)
                    ->class('tc-field-queue')
                    ->width('50%'),

                Select::make('task.status')
                    ->options(TaskStatusEnum::options())
                    ->value($this->query->get('task.status'))
                    ->title(__('task.status.label'))
                    ->required()
                    ->width('50%'),
            ])->fullWidth(),

            Group::make([
                Select::make('task.task_category_id')
                    ->fromModel(TaskCategory::class, 'name', 'id')
                    ->title(__('task.task_category_id'))
                    ->required()
                    ->width('50%'),

                Select::make('task.priority')
                    ->options(
                        collect(TaskPriorityEnum::orderedCases())
                            ->mapWithKeys(fn ($p) => [$p->value => "{$p->code()} · {$p->label()}"])
                            ->all()
                    )
                    ->title('Приоритет задачи')
                    ->required()
                    ->help('P0 — критично, P3 — обычная очередь')
                    ->value(TaskPriorityEnum::MEDIUM->value)
                    ->width('50%'),
            ])->fullWidth(),

            Group::make([
                Select::make('task.type_task')
                    ->options(TaskTypeEnum::options())
                    ->title('Тип задачи')
                    ->required()
                    ->help('Выберите тип задачи')
                    ->width('50%'),

                Input::make('task.estimation_hours')
                    ->type('number')
                    ->title('Оценка в часах')
                    ->step('0.5')
                    ->min(0)
                    ->help('Плановое время. Не зависит от фактического трекинга.')
                    ->readonly()
                    ->width('50%'),
            ])->fullWidth(),

            Group::make([
                DateTimer::make('task.end_datetime')
                    ->title('Дедлайн')
                    ->enableTime()
                    ->allowInput()
                    ->help('Срок сдачи задачи')
                    ->width('50%'),
            ])->fullWidth(),

            Quill::make('task.description')->toolbar(["text", "color", "header", "list", "format"])
                ->title(__('task.description')),

            Upload::make('task.attachments')
                ->title('Прикрепленные файлы')
                ->acceptedFiles('image/*,application/pdf,.psd,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx,.txt,.exe,.msi')
                ->storage('public')
                ->maxFileSize(UploadLimits::maxMb(256))
                ->help('До 256 МБ на файл. Форматы: изображения, PDF, архивы, документы, EXE/MSI'),
        ];
    }
}
