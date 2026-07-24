<?php

declare(strict_types=1);

namespace App\Orchid\Presenters;

use Laravel\Scout\Builder;
use Orchid\Screen\Contracts\Personable;
use Orchid\Screen\Contracts\Searchable;
use Orchid\Support\Presenter;

class UserPresenter extends Presenter implements Personable, Searchable
{
    public function label(): string
    {
        return 'Users';
    }

    public function title(): string
    {
        return $this->entity->displayName();
    }

    public function subTitle(): string
    {
        $roles = $this->entity->roleLabels();

        if ($roles === '') {
            return 'Без роли';
        }

        return $roles;
    }

    public function url(): string
    {
        return route('platform.systems.users.edit', $this->entity);
    }

    public function image(): ?string
    {
        $hash = md5(strtolower(trim($this->entity->email)));
        $default = urlencode('https://raw.githubusercontent.com/orchidsoftware/.github/main/web/avatars/gravatar.png');

        return "https://www.gravatar.com/avatar/$hash?d=$default";
    }

    public function perSearchShow(): int
    {
        return 3;
    }

    public function searchQuery(?string $query = null): Builder
    {
        return $this->entity->search($query);
    }
}
