<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class PriorityBarChart extends Chart
{
    protected $type = self::TYPE_BAR;

    protected $height = 320;

    protected $target = 'chart_priority';
}
