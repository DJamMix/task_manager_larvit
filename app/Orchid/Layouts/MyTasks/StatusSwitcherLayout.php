<?php

namespace App\Orchid\Layouts\MyTasks;

use App\CoreLayer\Enums\TaskStatusEnum;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Rows;

class StatusSwitcherLayout extends Rows
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
        $task = $this->query->get('task');
        $isExecutor = auth()->id() == $task['executor']['id'];
        $currentStatus = $task['status'];

        if ($currentStatus === TaskStatusEnum::DEMO->value) {
            return [
                Label::make('demo_info')
                    ->title('Статус задачи')
                    ->value('Задача находится на демонстрации заказчику. Ожидайте решения - задача будет либо принята, либо возвращена на доработку.')
                    ->hr()
            ];
        }

        $buttons = [];

        if ($isExecutor) {
            switch ($currentStatus) {
                case TaskStatusEnum::IN_PROGRESS->value:
                    // Только одна кнопка для перевода на тестирование
                    $buttons[] = Button::make('Перевести в тестирование на stage')
                        ->method('changeStatus')
                        ->parameters([
                            'status' => TaskStatusEnum::TESTING_STAGE->value
                        ])
                        ->icon('flask')
                        ->class('btn btn-info')
                        ->confirm('Вы уверены, что хотите перевести задачу на тестирование stage?');
                    break;

                case TaskStatusEnum::TESTING_STAGE->value:
                    // Кнопка для возврата в работу
                    $buttons[] = Button::make('Вернуть в работу')
                        ->method('changeStatus')
                        ->parameters([
                            'status' => TaskStatusEnum::IN_PROGRESS->value
                        ])
                        ->icon('arrow-left')
                        ->class('btn btn-warning')
                        ->confirm('Вы уверены, что хотите вернуть задачу в работу?');

                    $buttons[] = Button::make('Перевести в тестирование на PROD')
                        ->method('changeStatus')
                        ->parameters([
                            'status' => TaskStatusEnum::TESTING_PROD->value
                        ])
                        ->icon('flask')
                        ->class('btn btn-info')
                        ->confirm('Вы уверены, что хотите перевести задачу на тестирование prod?');
                    break;

                // Для остальных статусов кнопки не показываем
                case TaskStatusEnum::TESTING_PROD->value:
                    $buttons[] = Button::make('Вернуть в тестирование STAGE')
                        ->method('changeStatus')
                        ->parameters([
                            'status' => TaskStatusEnum::TESTING_STAGE->value
                        ])
                        ->icon('arrow-left')
                        ->class('btn btn-warning')
                        ->confirm('Вы уверены, что хотите вернуть задачу в тестирование STAGE?');

                    $buttons[] = Button::make('Перевести в ДЕМО')
                        ->method('changeStatus')
                        ->parameters([
                            'status' => TaskStatusEnum::DEMO->value
                        ])
                        ->icon('tv')
                        ->class('btn btn-info')
                        ->confirm('Вы уверены, что хотите перевести задачу в ДЕМО? Это ФИНАЛЬНЫЙ СТАТУС!!!');
                    break;

                default:
                    // Никаких кнопок для этих статусов
                    break;
            }
        }

        return $buttons;
    }
}
