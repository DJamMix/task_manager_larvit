<?php

namespace App\Orchid\Layouts\Act\Components;

use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Actions\Link;

class ActTasksList
{
    public static function make($tasks, bool $isEditable = false): array
    {
        $fields = [];
        
        if (empty($tasks) || (is_object($tasks) && $tasks->isEmpty())) {
            $fields[] = Label::make('')
                ->title('Нет задач')
                ->class('text-center text-muted');
            return $fields;
        }
        
        $headers = [
            $isEditable ? Label::make('')->title('') : null,
            Label::make('')->title('Задача'),
            Label::make('')->title('Проект'),
            Label::make('')->title('Часы'),
        ];
        
        $fields[] = Group::make(array_filter($headers));
        
        foreach ($tasks as $index => $task) {
            $fields[] = self::createTaskRow($task, $index, $isEditable);
        }
        
        return $fields;
    }
    
    private static function createTaskRow($task, $index, bool $isEditable): Group
    {
        $hours = 0.0;
        $taskId = 0;
        $taskTitle = '';
        $projectName = '';
        $isSelected = true;
        
        if (is_object($task)) {
            $hours = (float) ($task->pivot->hours ?? $task->estimation_hours ?? 0);
            $taskId = $task->id ?? 0;
            $taskTitle = $task->name ?? $task->title ?? '';
            $projectName = is_object($task->project ?? null) ? ($task->project->name ?? '') : '';
            $isSelected = $task->selected ?? true;
        } elseif (is_array($task)) {
            $hours = (float) ($task['estimation_hours'] ?? $task['pivot']['hours'] ?? 0);
            $taskId = $task['id'] ?? 0;
            $taskTitle = $task['name'] ?? $task['title'] ?? '';
            $projectName = $task['project'] ?? '';
            $isSelected = $task['selected'] ?? true;
        }
        
        $hours = max($hours, 0.25);
        
        $hoursField = $isEditable 
            ? Input::make("tasks[{$index}][hours]")
                ->type('number')
                ->value($hours)
                ->step(0.25)
                ->min(0.25)
                ->required()
                ->title('Часы')
                ->help('Количество часов. Можно изменить.')
                ->class('text-right')
            : Input::make('')
                ->type('text')
                ->value(number_format($hours, 2, ',', ' '))
                ->readonly();
        
        $fields = [
            $isEditable 
                ? CheckBox::make("tasks[{$index}][selected]")
                    ->value($taskId)
                    ->checked((bool) $isSelected)
                    ->sendTrueOrFalse()
                : null,
            
            $isEditable ? Input::make("tasks[{$index}][id]")->type('hidden')->value($taskId) : null,
            
            Link::make((string) $taskTitle)
                ->href(route('platform.systems.tasks.edit', $taskId))
                ->target('_blank'),
            
            Input::make('')
                ->type('text')
                ->value((string) $projectName)
                ->readonly(),
            
            $hoursField,
        ];
        
        return Group::make(array_filter($fields));
    }
}