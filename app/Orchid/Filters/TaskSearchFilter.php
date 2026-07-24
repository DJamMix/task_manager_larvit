<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;

class TaskSearchFilter extends Filter
{
    public function name(): string
    {
        return 'Поиск';
    }

    public function parameters(): array
    {
        return ['search'];
    }

    public function run(Builder $builder): Builder
    {
        $search = $this->request->get('search');

        if (empty($search)) {
            return $builder;
        }

        return $builder->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function display(): array
    {
        return [
            Input::make('search')
                ->type('text')
                ->value($this->request->get('search'))
                ->placeholder('Поиск...')
                ->title(''),
        ];
    }
}