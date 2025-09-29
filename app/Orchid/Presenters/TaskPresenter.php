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
        $description = $this->entity->description ?? 'Нет описания';
        
        // Убираем HTML теги
        $cleanDescription = strip_tags($description);
        
        // Обрезаем длинное описание
        if (mb_strlen($cleanDescription) > 100) {
            $cleanDescription = mb_substr($cleanDescription, 0, 100) . '...';
        }
        
        // Добавляем информацию о проекте и статусе
        $parts = [];
        
        if ($this->entity->project) {
            $parts[] = 'Проект: ' . $this->entity->project->name;
        }
        
        if ($this->entity->status) {
            $parts[] = 'Статус: ' . $this->entity->status->label();
        }
        
        if ($this->entity->priority) {
            $parts[] = 'Приоритет: ' . $this->entity->priority->label();
        }
        
        $additionalInfo = implode(' | ', $parts);
        
        return $cleanDescription . ($additionalInfo ? " | " . $additionalInfo : "");
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