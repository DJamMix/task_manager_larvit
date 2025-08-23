<?php

namespace App\Orchid\Filters;

use App\CoreLayer\Enums\TaskPriorityEnum;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class TaskPriorityFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Приоритет';
    }

    /**
     * The array of matched parameters.
     *
     * @return array|null
     */
    public function parameters(): ?array
    {
        return ['priority'];
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
        $priorities = $this->request->get('priority', []);
        
        if (!empty($priorities)) {
            return $builder->whereIn('priority', $priorities);
        }

        return $builder;
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [
            Select::make('priority')
                ->multiple() // Множественный выбор
                ->options(TaskPriorityEnum::options()) // Используем метод из enum
                ->title('Приоритет')
                ->empty('Все приоритеты')
                ->value($this->request->get('priority', [])), // Устанавливаем выбранные значения
        ];
    }
}
