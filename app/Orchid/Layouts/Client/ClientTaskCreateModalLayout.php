<?php

namespace App\Orchid\Layouts\Client;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskTypeEnum;
use App\Models\TaskCategory;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
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
            Label::make('create_hint')
                ->value('Опишите задачу понятно для команды. После создания она уйдёт на согласование.'),

            Input::make('task.name')
                ->type('text')
                ->max(255)
                ->required()
                ->title('Название')
                ->placeholder('Кратко: что нужно сделать'),

            Group::make([
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
            ])->fullWidth(),

            Select::make('task.priority')
                ->options(
                    collect(TaskPriorityEnum::orderedCases())
                        ->mapWithKeys(fn ($p) => [$p->value => "{$p->code()} · {$p->label()}"])
                        ->all()
                )
                ->title('Приоритет')
                ->required()
                ->value(TaskPriorityEnum::MEDIUM->value)
                ->help('P0 — критично, P3 — обычная очередь'),

            Quill::make('task.description')
                ->toolbar(['text', 'list', 'quote', 'format'])
                ->height('220px')
                ->title('Описание')
                ->placeholder('Контекст, шаги, ожидаемый результат. Код — кнопкой </>')
                ->required(),

            Upload::make('task.attachments')
                ->title('Файлы')
                ->acceptedFiles('image/*,application/pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.txt,.psd,.fig')
                ->storage('public')
                ->maxFileSize(50)
                ->maxFiles(10)
                ->help('Скриншоты, макеты, документы — до 50 МБ'),
        ];
    }
}
