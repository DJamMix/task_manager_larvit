<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateRange;

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
        return $builder->whereBetween('created_at', $this->request->get('created_at'));
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