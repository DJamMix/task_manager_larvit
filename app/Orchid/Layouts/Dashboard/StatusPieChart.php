<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class StatusPieChart extends Chart
{
    protected $type = self::TYPE_PIE;

    protected $height = 320;

    protected $target = 'chart_status';

    protected $maxSlices = 12;
}
