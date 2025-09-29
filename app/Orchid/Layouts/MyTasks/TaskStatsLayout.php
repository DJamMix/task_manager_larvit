<?php

namespace App\Orchid\Layouts\MyTasks;

use Orchid\Screen\Layouts\Metric;

class TaskStatsLayout extends Metric
{
    /**
     * @var string
     */
    protected $target = 'stats';

    /**
     * @return array
     */
    protected function metrics(): array
    {
        return [
            [
                'title'  => 'Всего задач',
                'value'  => 'total',
                'icon'   => 'folder',
                'color'  => 'primary',
                'target' => '?',
            ],
            [
                'title'  => 'Срочные',
                'value'  => 'urgent',
                'icon'   => 'fire',
                'color'  => 'danger',
                'target' => '?priority=emergency,blocker',
            ],
            [
                'title'  => 'Высокий приоритет',
                'value'  => 'high_priority',
                'icon'   => 'clock',
                'color'  => 'warning',
                'target' => '?priority=high',
            ],
            [
                'title'  => 'В работе',
                'value'  => 'in_progress',
                'icon'   => 'refresh',
                'color'  => 'info',
                'target' => '?status=in_progress',
            ],
            [
                'title'  => 'Сегодня создано',
                'value'  => 'today_created',
                'icon'   => 'calendar',
                'color'  => 'success',
                'target' => '?today=1',
            ],
        ];
    }
}