<?php

namespace App\Orchid\Screens\Client;

use App\Models\User;
use App\Orchid\Layouts\Client\ClientListProjectLayout;
use Orchid\Screen\Screen;

class ClientListProjectScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        $user = User::find(auth()->id());

        return [
            'projects' => $user->projects
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Мои проекты';
    }

    public function description(): string|null
    {
        return 'Список проектов, перейдя в проекты, можно посмотреть задачи по-этому проекту.';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.client.projects',
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            ClientListProjectLayout::class,
        ];
    }
}
