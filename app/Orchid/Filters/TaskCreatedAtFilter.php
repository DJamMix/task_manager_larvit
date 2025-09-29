<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateRange;
use Carbon\Carbon;

class TaskCreatedAtFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Дата создания';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['created_at'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $dates = $this->request->get('created_at');
        
        if (empty($dates) || !is_array($dates)) {
            return $builder;
        }

        $startDate = $dates['start'] ?? null;
        $endDate = $dates['end'] ?? null;

        if ($startDate && $endDate) {
            // Преобразуем в объекты Carbon и устанавливаем время
            $start = Carbon::parse($startDate)->startOfDay(); // 00:00:00
            $end = Carbon::parse($endDate)->endOfDay();       // 23:59:59
            
            return $builder->whereBetween('created_at', [$start, $end]);
        }

        if ($startDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            return $builder->where('created_at', '>=', $start);
        }

        if ($endDate) {
            $end = Carbon::parse($endDate)->endOfDay();
            return $builder->where('created_at', '<=', $end);
        }

        return $builder;
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            DateRange::make('created_at')
                ->value($this->request->get('created_at'))
                ->title('Дата создания')
        ];
    }
}