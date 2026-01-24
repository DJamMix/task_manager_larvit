<?php

namespace App\Orchid\Layouts\Task;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\CoreLayer\Enums\TaskTypeEnum;
use App\Models\Project;
use App\Models\TaskCategory;
use App\Models\User;
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

        return [
            Group::make([
                Input::make('task.name')
                    ->type('text')
                    ->max(255)
                    ->required()
                    ->title(__('task.name'))
                    ->placeholder(__('task.name'))
                    ->width('50%'),

                Select::make('task.creator_id')
                    ->fromModel(User::class, 'name', 'id')
                    ->required()
                    ->title(__('task.creator_id'))
                    ->width('50%'),
            ])->fullWidth(),

            Group::make([
                Select::make('task.executor_id')
                    ->fromModel(User::class, 'name', 'id')
                    ->title(__('task.executor_id'))
                    ->empty('Не выбран')
                    ->width('50%'),

                Select::make('task.project_id')
                    ->fromModel(Project::class, 'name', 'id')
                    ->title(__('task.project_id'))
                    ->required()
                    ->width('50%'),
            ])->fullWidth(),

            Group::make([
                Select::make('task.status')
                    ->options(TaskStatusEnum::options())
                    ->value($this->query->get('task.status'))
                    ->title(__('task.status.label'))
                    ->required()
                    ->width('50%'),

                Select::make('task.task_category_id')
                    ->fromModel(TaskCategory::class, 'name', 'id')
                    ->title(__('task.task_category_id'))
                    ->required()
                    ->width('50%'),
            ])->fullWidth(),

            Group::make([
                Select::make('task.priority')
                    ->options(TaskPriorityEnum::options())
                    ->title('Приоритет задачи')
                    ->required()
                    ->help('Выберите приоритет выполнения задачи')
                    ->value(TaskPriorityEnum::MEDIUM->value)
                    ->width('50%'),

                Select::make('task.type_task')
                    ->options(TaskTypeEnum::options())
                    ->title('Тип задачи')
                    ->required()
                    ->help('Выберите тип задачи')
                    ->width('50%'),
            ])->fullWidth(),

            Group::make([
                Input::make('task.estimation_hours')
                    ->type('number')
                    ->title('Оценка в часах')
                    ->step('0.5')
                    ->min(0)
                    ->help('Плановое время на выполнение задачи')
                    ->readonly()
                    ->width('50%'),
            ])->fullWidth(),

            // DateTimer::make('task.start_datetime')
            //     ->title(__('task.start_datetime'))
            //     ->serverFormat('Y-m-d H:i:s')
            //     ->enableTime()
            //     ->allowInput()
            //     ->withQuickDates([
            //         'Сегодня'     => now(),
            //         'Завтра'      => now()->addDay(),
            //         'Через неделю' => now()->addWeek(),
            //     ]),

            // DateTimer::make('task.end_datetime')
            //     ->title(__('task.end_datetime'))
            //     ->serverFormat('Y-m-d H:i:s')
            //     ->enableTime()
            //     ->allowInput()
            //     ->withQuickDates([
            //         'Сегодня'     => now(),
            //         'Завтра'      => now()->addDay(),
            //         'Через неделю' => now()->addWeek(),
            //     ]),

            // Input::make('task.cost_estimation')
            //     ->type('number')
            //     ->title(__('task.cost_estimation'))
            //     ->step('0.01')
            //     ->min(0),

            // CheckBox::make('task.pay_status')
            //     ->title(__('task.pay_status'))
            //     ->sendTrueOrFalse(),

            // Input::make('task.hours_spent')
            //     ->title(__('task.hours_spent'))
            //     ->type('number')
            //     ->step('0.01')
            //     ->min(0)
            //     ->value(0),

            Quill::make('task.description')->toolbar(["text", "color", "header", "list", "format"])
                ->title(__('task.description')),

            Upload::make('task.attachments')
                ->title('Прикрепленные файлы')
                ->acceptedFiles('image/*,application/pdf,.psd')
                ->storage('public')
                ->maxFileSize(1024)
                ->help('Допустимые форматы: JPG, PNG, PDF, PSD. Макс. размер: 1 ГБ'),
        ];
    }
}
