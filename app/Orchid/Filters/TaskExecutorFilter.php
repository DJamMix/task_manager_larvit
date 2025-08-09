<?php

namespace App\Orchid\Filters;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class TaskExecutorFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Исполнитель';
    }

    /**
     * The array of matched parameters.
     *
     * @return array|null
     */
    public function parameters(): ?array
    {
        return ['executor'];
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
        $executorId = $this->request->get('executor');

        if ($executorId === null || $executorId === '') {
            return $builder;
        }

        if ($executorId === 'null') {
            return $builder->whereNull('executor_id');
        }

        return $builder->where('executor_id', $executorId);
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        $users = User::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
            ->toArray();

        // Добавляем специальный пункт в начало массива
        $options = ['null' => 'Без исполнителя'] + $users;

        return [
            Select::make('executor')
                ->options($options)
                ->empty('Все задачи')
                ->title('Фильтр по исполнителю')
                ->value($this->request->get('executor'))
                ->help('Выберите исполнителя или "Без исполнителя"'),
        ];
    }
}
