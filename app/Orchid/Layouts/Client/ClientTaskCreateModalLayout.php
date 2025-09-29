<?php

namespace App\Orchid\Layouts\Client;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskTypeEnum;
use App\Models\TaskCategory;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Attach;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Quill;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Upload;
use Orchid\Screen\Layouts\Rows;

class ClientTaskCreateModalLayout extends Rows
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
        return [
            Input::make('task.name')
                ->type('text')
                ->max(255)
                ->required()
                ->title(__('task.name'))
                ->placeholder(__('task.name')),

            Select::make('task.task_category_id')
                ->fromModel(TaskCategory::class, 'name', 'id')
                ->title(__('task.task_category_id'))
                ->required(),

            Select::make('task.type_task')
                ->options(TaskTypeEnum::options())
                ->title('Тип задачи')
                ->required()
                ->help('Выберите тип задачи')
                ->value(TaskTypeEnum::DEFAULT->value),

            Label::make('priority_help')
                ->title('Описание приоритетов:')
                ->help(collect(TaskPriorityEnum::orderedCases())
                    ->map(fn($p) => "<b>{$p->label()}:</b> {$p->description()}")
                    ->join('<br>')),

            Select::make('task.priority')
                ->options(TaskPriorityEnum::options())
                ->title('Приоритет задачи')
                ->required()
                ->help('Выберите приоритет выполнения задачи')
                ->value(TaskPriorityEnum::MEDIUM->value),

            Quill::make('task.description')->toolbar(["text", "color", "header", "list", "format"])
                ->title(__('task.description'))
                ->required(),

            Upload::make('task.attachments')
                ->title('Прикрепленные файлы')
                ->acceptedFiles('image/*,application/pdf,.psd')
                ->storage('public')
                ->maxFileSize(1024)
                ->help('Допустимые форматы: JPG, PNG, PDF, PSD. Макс. размер: 1 ГБ'),
        ];
    }
}
