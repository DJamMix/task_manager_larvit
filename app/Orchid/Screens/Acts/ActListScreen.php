<?php

namespace App\Orchid\Screens\Acts;

use App\Models\Act;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Link;
use Orchid\Support\Facades\Layout;
use App\Orchid\Layouts\Act\ActListLayout;

class ActListScreen extends Screen
{
    public function query(): iterable
    {
        return [
            'acts' => Act::with('tasks')
                ->latest()
                ->paginate(10),
        ];
    }

    public function name(): ?string
    {
        return 'Акты';
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Создать новый акт')
                ->icon('plus')
                ->route('platform.systems.acts.create'),
        ];
    }

    public function layout(): iterable
    {
        return [
            ActListLayout::class,
        ];
    }
}