<?php

namespace App\Orchid\Layouts\Act\Steps;

use App\CoreLayer\Enums\TaskStatusEnum;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Actions\Button;

class TaskSelectionStep
{
    public static function fields($tasks, $project, $actData, $hasDuplicates): array
    {
        $fields = [];

        $fields[] = Label::make('step_header')
            ->title('Выбор задач')
            ->class('text-primary h3 mb-4');
        
        $fields[] = Label::make('step_description')
            ->title('Выберите задачи для включения в акт')
            ->class('text-muted mb-4');

        if ($actData) {
            $fields[] = Label::make('act_info_title')
                ->title('Информация об акте')
                ->class('h4 mb-3');
            
            $fields = array_merge($fields, self::getActInfoFields($actData, $project));
        }

        if ($hasDuplicates) {
            $fields[] = Label::make('duplicates_warning_top')
                ->title('⚠ Внимание! Некоторые задачи уже используются в других актах.')
                ->class('alert alert-warning mt-3');
        }

        if (empty($tasks)) {
            $fields[] = Label::make('no_tasks')
                ->title('В выбранном проекте нет задач')
                ->class('text-center text-muted my-4');
            
            $fields[] = Button::make('Выбрать другой проект')
                ->method('previousStep')
                ->icon('arrow-left')
                ->class('btn btn-outline-secondary w-100');
        } else {
            $fields[] = Group::make([
                Button::make('Выбрать все')
                    ->method('selectAllTasks')
                    ->icon('check')
                    ->class('btn btn-outline-primary mr-2'),
            ]);

            foreach ($tasks as $index => $task) {
                $fields = array_merge($fields, self::createTaskRow($task, $index));
            }
        }

        return $fields;
    }

    private static function getActInfoFields($actData, $project): array
    {
        return [
            Group::make([
                Input::make('')
                    ->type('text')
                    ->value('Номер акта: ' . ($actData['number'] ?? ''))
                    ->readonly()
                    ->style('color: #000000; font-weight: 500;'),
                Input::make('')
                    ->type('text')
                    ->value('Дата: ' . date('d.m.Y', strtotime($actData['date'] ?? now())))
                    ->readonly()
                    ->style('color: #000000; font-weight: 500;'),
            ]),

            Group::make([
                Input::make('')
                    ->type('text')
                    ->value('Заказчик: ' . ($actData['customer'] ?? ''))
                    ->readonly()
                    ->style('color: #000000; font-weight: 500;'),
                Input::make('')
                    ->type('text')
                    ->value('Исполнитель: ' . ($actData['executor'] ?? ''))
                    ->readonly()
                    ->style('color: #000000; font-weight: 500;'),
            ]),

            Input::make('')
                ->type('text')
                ->value('Проект: ' . ($project->name ?? ''))
                ->readonly()
                ->style('color: #000000; font-weight: 500;')
                ->class('mb-4'),
        ];
    }

    private static function createTaskRow($task, $index): array
    {
        $hours = (float) ($task['estimation_hours'] ?? 0);
        if ($hours < 0.25) $hours = 0.25;
        
        $isSelected = $task['selected'] ?? false;
        
        // Получаем статус задачи
        $status = $task['status'] ?? 'new';
        $statusEnum = TaskStatusEnum::tryFrom($status);
        $statusLabel = $statusEnum?->label() ?? 'Неизвестно';
        $statusColor = $statusEnum?->color() ?? '#6c757d';
        
        $rowFields = [
            Group::make([
                CheckBox::make("tasks[{$index}][selected]")
                    ->value($task['id'] ?? 0)
                    ->checked($isSelected)
                    ->sendTrueOrFalse()
                    ->style('width: 50px;'),
                    
                Input::make("tasks[{$index}][id]")
                    ->type('hidden')
                    ->value($task['id'] ?? 0),
                    
                Input::make('')
                    ->type('text')
                    ->value($task['title'] ?? '')
                    ->readonly()
                    ->style('flex: 3; border: none; background: transparent; box-shadow: none; color: #000000; font-weight: 500;'),
                    
                // Столбец статуса
                Input::make('')
                    ->type('text')
                    ->value($statusLabel)
                    ->readonly()
                    ->style('flex: 1; border: none; background: transparent; box-shadow: none; color: ' . $statusColor . '; font-weight: 500;'),
                    
                Input::make('')
                    ->type('text')
                    ->value($task['project'] ?? '')
                    ->readonly()
                    ->style('flex: 1; border: none; background: transparent; box-shadow: none; color: #000000; font-weight: 500;'),
                    
                Input::make("tasks[{$index}][hours]")
                    ->type('number')
                    ->value($hours)
                    ->step(0.25)
                    ->min(0.25)
                    ->required()
                    ->title('Часы')
                    ->help('Количество часов по оценке задачи.')
                    ->style('width: 120px; color: #000000;'),
            ]),
        ];
        
        if (!empty($task['used_in_acts'])) {
            $actsInfo = array_map(function($act) {
                return "Акт №{$act['number']} от {$act['date']}";
            }, $task['used_in_acts']);
            
            $actsText = implode(', ', $actsInfo);
            
            $rowFields[] = Label::make("duplicate_info_{$index}")
                ->title("⚠ Эта задача уже используется в: " . $actsText)
                ->class('text-warning small ml-5')
                ->style('font-size: 0.875rem; margin-top: -5px; margin-bottom: 5px;');
        }
        
        $rowFields[] = Label::make('divider_' . $index)
            ->title('')
            ->class('border-bottom my-2');
            
        return $rowFields;
    }
}