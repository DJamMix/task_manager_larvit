<?php

namespace App\Orchid\Layouts\Client;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskTypeEnum;
use App\Models\TaskCategory;
use App\Support\UploadLimits;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Quill;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Upload;
use Orchid\Screen\Layouts\Rows;

class ClientTaskCreateModalLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        return [
            Input::make('task.name')
                ->type('text')
                ->max(255)
                ->required()
                ->title('Название')
                ->placeholder('Название задачи')
                ->class('tc-field-title'),

            Group::make([
                Select::make('task.task_category_id')
                    ->fromModel(TaskCategory::class, 'name', 'id')
                    ->title('Категория')
                    ->required()
                    ->empty('Категория')
                    ->class('tc-field-chip'),

                Select::make('task.type_task')
                    ->options(TaskTypeEnum::options())
                    ->title('Тип')
                    ->required()
                    ->value(TaskTypeEnum::DEFAULT->value)
                    ->class('tc-field-chip'),

                Select::make('task.priority')
                    ->options(
                        collect(TaskPriorityEnum::orderedCases())
                            ->mapWithKeys(fn ($p) => [$p->value => "{$p->code()} {$p->label()}"])
                            ->all()
                    )
                    ->title('Приоритет')
                    ->required()
                    ->value(TaskPriorityEnum::MEDIUM->value)
                    ->class('tc-field-chip'),
            ])->fullWidth()->alignEnd(),

            Quill::make('task.description')
                ->toolbar(['text', 'list', 'quote'])
                ->height('160px')
                ->title('Описание')
                ->placeholder('Описание, шаги, критерии. Код — </>')
                ->required(),

            Upload::make('task.attachments')
                ->title('Вложения')
                ->acceptedFiles('image/*,application/pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.txt,.psd,.fig')
                ->storage('public')
                ->maxFileSize(UploadLimits::maxMb(50))
                ->maxFiles(8),
        ];
    }
}
