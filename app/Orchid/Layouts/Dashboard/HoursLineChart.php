<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class HoursLineChart extends Chart
{
    protected $type = self::TYPE_LINE;

    protected $height = 300;

    protected $target = 'chart_hours';

    protected $lineOptions = [
        'spline' => 1,
        'regionFill' => 1,
        'hideDots' => 0,
        'hideLine' => 0,
        'heatline' => 0,
        'dotSize' => 3,
    ];
}
