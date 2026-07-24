<?php

namespace App\Orchid\Layouts\Project;

use App\Models\User;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class ProjectEditLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        return [
            Input::make('project.name')
                ->type('text')
                ->max(255)
                ->required()
                ->title(__('model_project.title'))
                ->placeholder(__('model_project.title')),

            TextArea::make('project.description')
                ->title('Описание')
                ->rows(3)
                ->help('Кратко: что за клиент / продукт, чтобы команде было проще ориентироваться'),

            CheckBox::make('project.is_active')
                ->title('Активный проект')
                ->sendTrueOrFalse()
                ->value(true)
                ->help('Неактивные проекты можно скрывать из повседневной работы'),

            Select::make('project.members.')
                ->fromModel(User::class, 'name')
                ->multiple()
                ->title('Команда проекта')
                ->help('Сотрудники, которым доступен этот проект в переключателе слева'),
        ];
    }
}
