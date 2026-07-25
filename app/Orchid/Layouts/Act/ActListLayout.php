<?php

namespace App\Orchid\Layouts\Act;

use App\Models\Act;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ActListLayout extends Table
{
    protected $target = 'acts';

    protected function striped(): bool
    {
        return true;
    }

    protected function columns(): iterable
    {
        return [
            TD::make('number', 'Номер')
                ->render(fn (Act $act) => Link::make($act->number)
                    ->route('platform.systems.acts.edit', $act)
                    ->class('act-list-number'))
                ->sort(),

            TD::make('date', 'Дата')
                ->render(fn (Act $act) => $act->date?->format('d.m.Y') ?? '—')
                ->sort(),

            TD::make('project_id', 'Проект')
                ->render(fn (Act $act) => e($act->project?->name ?? '—')),

            TD::make('customer', 'Заказчик')
                ->render(fn (Act $act) => e(\Illuminate\Support\Str::limit((string) $act->customer, 40))),

            TD::make('executor', 'Исполнитель')
                ->render(fn (Act $act) => e(\Illuminate\Support\Str::limit((string) $act->executor, 32))),

            TD::make('total_tasks', 'Задач')
                ->align(TD::ALIGN_CENTER)
                ->render(fn (Act $act) => (string) (int) $act->total_tasks),

            TD::make('total_hours', 'Часов')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn (Act $act) => number_format((float) $act->total_hours, 2, ',', ' ')),

            TD::make('actions', '')
                ->cantHide()
                ->render(fn (Act $act) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Открыть')
                            ->icon('bs.pencil')
                            ->route('platform.systems.acts.edit', $act),
                        Link::make('Скачать Word')
                            ->icon('bs.download')
                            ->route('platform.systems.acts.download', $act)
                            ->target('_blank'),
                    ])),
        ];
    }
}
