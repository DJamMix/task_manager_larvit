<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class ThroughputLineChart extends Chart
{
    protected $type = self::TYPE_LINE;

    protected $height = 300;

    protected $target = 'chart_throughput';

    protected $lineOptions = [
        'spline' => 1,
        'regionFill' => 0,
        'hideDots' => 0,
        'hideLine' => 0,
        'heatline' => 0,
        'dotSize' => 3,
    ];
}
