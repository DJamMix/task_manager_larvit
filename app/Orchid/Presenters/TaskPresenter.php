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
        // Для отладки - логируем запрос
        \Log::debug('Task search', [
            'query' => $query,
            'user_id' => auth()->id(),
            'scout_driver' => config('scout.driver')
        ]);

        // Если Scout не настроен, используем fallback
        if (config('scout.driver') === 'null' || empty($query)) {
            return $this->fallbackSearch($query);
        }

        try {
            return $this->entity->search($query)
                ->where('executor_id', auth()->id())
                ->query(function ($builder) {
                    $builder->with(['project', 'executor', 'category']);
                });
        } catch (\Exception $e) {
            \Log::error('Scout search failed: ' . $e->getMessage());
            return $this->fallbackSearch($query);
        }
    }

    /**
     * Fallback поиск через Eloquent
     */
    protected function fallbackSearch(?string $query): Builder
    {
        $model = $this->entity;
        
        // Создаем mock Builder для совместимости
        $builder = $model->where('executor_id', auth()->id());
        
        if (!empty($query)) {
            $builder->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });
        }
        
        $builder->with(['project', 'executor', 'category'])
                ->orderBy('created_at', 'desc')
                ->limit($this->perSearchShow());

        // Возвращаем как Scout Builder для совместимости
        return new \Laravel\Scout\Builder($model, $query, function($model, $query) use ($builder) {
            return $builder->get();
        });
    }
    
    /**
     * @return int
     */
    public function perSearchShow(): int
    {
        return 5;
    }
}