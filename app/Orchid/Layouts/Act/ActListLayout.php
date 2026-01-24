<?php

namespace App\Orchid\Layouts\Act;

use App\Models\Act;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Orchid\Support\Color;

class ActListLayout extends Table
{
    protected $target = 'acts';

    protected function columns(): iterable
    {
        return [
            TD::make('number', 'Номер')
                ->render(fn(Act $act) => Link::make($act->number)->route('platform.systems.acts.edit', $act))
                ->sort(),

            TD::make('date', 'Дата')
                ->render(fn(Act $act) => $act->date->format('d.m.Y'))
                ->sort(),

            TD::make('customer', 'Заказчик'),
            TD::make('executor', 'Исполнитель'),
            TD::make('total_tasks', 'Задач')->alignCenter(),
            TD::make('total_hours', 'Часов')
                ->render(fn(Act $act) => number_format($act->total_hours, 2, ',', ' '))
                ->alignCenter(),
            
            TD::make('actions', 'Действия')
                ->render(fn(Act $act) => Link::make('Скачать')
                    ->icon('download')
                    ->route('platform.systems.acts.download', $act)
                    ->type(Color::DARK)
                    ->target('_blank')
                )->alignCenter(),
        ];
    }
}