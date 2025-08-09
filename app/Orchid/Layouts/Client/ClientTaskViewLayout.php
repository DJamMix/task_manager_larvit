<?php

namespace App\Orchid\Layouts\Client;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Quill;
use Orchid\Screen\Layouts\Rows;

class ClientTaskViewLayout extends Rows
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
                $this->createLabel('Название задачи', 'task.name')->width('100%'),
            ])->fullWidth(),

            Group::make([
                $this->createLabel('Проект', 'task.project.name')->width('50%'),
                $this->createLabel('Категория', 'task.category.name')->width('50%'),
            ])->fullWidth(),

            // Затраченное время
            Group::make([
                $this->createLabel('Статус задачи', 'task_status_label')->width('50%'),
                $this->createLabel('Оценка в часах от исполнителя', 'task.estimation_hours')->width('50%'),
            ])->fullWidth(),

            // Описание с фиксированной высотой
            Quill::make('task.description')
                ->toolbar([])
                ->title('Описание')
                ->readonly()
                ->rows(10)
                ->class('border rounded p-3 bg-light'),
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
