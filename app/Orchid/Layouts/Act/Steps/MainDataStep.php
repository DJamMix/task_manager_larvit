<?php

namespace App\Orchid\Layouts\Act\Steps;

use App\Models\Project;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Label;

class MainDataStep
{
    public static function fields(): array
    {
        $fields = [];

        $fields[] = Label::make('step_header')
            ->title('Основные данные акта')
            ->class('text-primary h3 mb-4');
        
        $fields[] = Label::make('step_description')
            ->title('Заполните основную информацию об акте и выберите проект')
            ->class('text-muted mb-4');
        
        $fields[] = Group::make([
            Input::make('act.number')
                ->type('text')
                ->max(255)
                ->required()
                ->title('Номер акта')
                ->placeholder('QR-001/2026'),
                
            DateTimer::make('act.date')
                ->required()
                ->title('Дата акта')
                ->format('Y-m-d'),
        ]);

        $fields[] = Input::make('act.customer')
            ->max(255)
            ->required()
            ->title('Заказчик')
            ->placeholder('Название компании');
            
        $fields[] = Input::make('act.executor')
            ->max(255)
            ->required()
            ->title('Исполнитель')
            ->placeholder('ФИО исполнителя');
        
        $fields[] = Select::make('project_id')
            ->fromModel(Project::class, 'name', 'id')
            ->required()
            ->title('Проект')
            ->help('Выберите проект для отображения задач')
            ->empty('Выберите проект');
            
        return $fields;
    }
}