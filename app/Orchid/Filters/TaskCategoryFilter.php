<?php

namespace App\Orchid\Filters;

use App\Models\TaskCategory;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class TaskCategoryFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Категория задачи';
    }

    /**
     * The array of matched parameters.
     *
     * @return array|null
     */
    public function parameters(): ?array
    {
        return ['filter.task_category_id'];
    }

    /**
     * Apply to a given Eloquent query builder.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function run(Builder $builder): Builder
    {
        return $builder->whereHas('category', function($query) {
            $query->where('id', $this->request->input('filter.task_category_id'));
        });
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [
            Select::make('filter.task_category_id')
                ->fromModel(TaskCategory::class, 'name', 'id')
                ->empty()
                ->value($this->request->input('filter.task_category_id'))
                ->title($this->name()),
        ];
    }
}
