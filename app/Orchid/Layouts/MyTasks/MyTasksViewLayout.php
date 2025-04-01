<?php

namespace App\Orchid\Layouts\MyTasks;

use Orchid\Screen\Actions\Button;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Group;
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
            Group::make([
                Button::make('Внести затраченные часы')
                    ->method('handler'),

                Button::make('Взял в работу')
                    ->method('handler'),

                Button::make('Сдать задачу')
                    ->method('handler'),
            ])->autoWidth(),

            Group::make([
                Label::make('task.name')
                    ->title('Название задачи'),
                    
                Label::make('task.creator.name')
                    ->title('Создатель задачи'),
            ])->autoWidth(),

            Group::make([
                Label::make('task.project.name')
                    ->title('Проект'),
                    
                Label::make('task.task_category.name')
                    ->title('Категория'),
            ])->autoWidth(),

            Group::make([
                Label::make('task.status_label')
                    ->title('Статус задачи'),
            ])->autoWidth(),

            Group::make([
                Label::make('task.start_datetime')
                    ->title('Дата начала'),
                    
                Label::make('task.end_datetime')
                    ->title('Дата сдачи'),
            ])->autoWidth(),

            Group::make([
                Label::make('task.hours_spent')
                    ->title('Затраченные часы'),

            ])->autoWidth(),

            Quill::make('task.description')
                ->toolbar([])
                ->title('Описание')
                ->readonly()
                ->rows(7),

            Group::make([
                Label::make('')
                    ->title('Прикрепленные файлы (СКОРО)'),
            ])->autoWidth(),
        ];
    }
}
