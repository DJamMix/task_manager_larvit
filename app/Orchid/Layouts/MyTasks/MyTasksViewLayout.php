<?php

namespace App\Orchid\Layouts\MyTasks;

use App\CoreLayer\Enums\TaskStatusEnum;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Quill;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class MyTasksViewLayout extends Rows
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
            // Основная информация в компактных группах
            Group::make([
                $this->createLabel('Название задачи', 'task.name')->width('50%'),
                $this->createLabel('Создатель задачи', 'task.creator.name')->width('50%'),
            ])->fullWidth(),

            Group::make([
                $this->createLabel('Проект', 'task.project.name')->width('50%'),
                $this->createLabel('Категория', 'task.task_category.name')->width('50%'),
            ])->fullWidth(),

            // Статус с цветным индикатором
            $this->createLabel('Статус задачи', 'task_status_label'),
            
            // Затраченное время
            Group::make([
                $this->createLabel('Затраченные часы', 'task.hours_spent')->width('50%'),
                $this->createLabel('Ваша оценка в часах', 'task.estimation_hours')->width('50%'),
            ])->fullWidth(),

            // Описание с фиксированной высотой
            Quill::make('task.description')
                ->toolbar([])
                ->title('Описание')
                ->readonly()
                ->rows(10)
                ->class('border rounded p-3 bg-light'),

            // Файлы
            Label::make('attachments')
                ->title('Прикрепленные файлы')
                ->class('h4 mt-3')
                ->value('Будет доступно в следующей версии'),
        ];
    }

    /**
     * Создает группу с меткой для единообразного отображения
     */
    protected function createLabelGroup(string $title, string $name): Group
    {
        return Group::make([
            $this->createLabel($title, $name)
        ])->fullWidth();
    }

    protected function createLabel(string $title, string $name): Label
    {
        return Label::make($name)
            ->title($title)
            ->class('form-control-plaintext border-bottom pb-2 mb-3');
    }
}
