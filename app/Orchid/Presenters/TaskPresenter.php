<?php

namespace App\Orchid\Presenters;

use Laravel\Scout\Builder;
use Orchid\Screen\Contracts\Searchable;
use Orchid\Support\Presenter;

class TaskPresenter extends Presenter implements Searchable
{
    /**
     * @return string
     */
    public function label(): string
    {
        return 'Задачи';
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->entity->name;
    }

    /**
     * @return string
     */
    public function subTitle(): string
    {
        $parts = [];
        
        if ($this->entity->project) {
            $parts[] = 'Проект: ' . $this->entity->project->name;
        }
        
        if ($this->entity->executor) {
            $parts[] = 'Исполнитель: ' . $this->entity->executor->name;
        }
        
        $parts[] = 'Статус: ' . $this->entity->status->label();
        $parts[] = 'Приоритет: ' . $this->entity->priority->label();

        return implode(' | ', $parts);
    }

    /**
     * @return string
     */
    public function url(): string
    {
        return route('platform.systems.tasks.edit', $this->entity);
    }

    /**
     * @return string|null
     */
    public function image(): ?string
    {
        return null;
    }
    
    /**
     * @param string|null $query
     *
     * @return Builder
     */
    public function searchQuery(string $query = null): Builder
    {
        return $this->entity->search($query);
    }

    /**
     * @return int
     */
    public function perSearchShow(): int
    {
        return 5;
    }
}