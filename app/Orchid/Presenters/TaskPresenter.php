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
        $userId = auth()->id();
        
        \Log::debug('Task search called', [
            'query' => $query,
            'user_id' => $userId,
            'authenticated' => auth()->check()
        ]);

        if (!$userId) {
            return new \Laravel\Scout\Builder($this->entity, $query, function() {
                return collect();
            });
        }

        if (empty($query)) {
            return $this->fallbackSearch($query);
        }

        try {
            // Для Meilisearch используем простой фильтр, а сложную логику в Eloquent
            return $this->entity->search($query)
                ->query(function ($builder) use ($userId, $query) {
                    $builder->where(function($q) use ($userId) {
                        $q->where('executor_id', $userId)
                        ->orWhere('creator_id', $userId);
                    })
                    ->where(function($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                    })
                    ->with(['project', 'executor', 'category'])
                    ->orderBy('created_at', 'desc')
                    ->limit($this->perSearchShow());
                });
                
        } catch (\Exception $e) {
            \Log::error('Scout search failed', [
                'error' => $e->getMessage(),
                'query' => $query,
                'user_id' => $userId
            ]);
            
            return $this->fallbackSearch($query);
        }
    }

    /**
     * Fallback поиск через Eloquent
     */
    protected function fallbackSearch(?string $query): Builder
    {
        $userId = auth()->id();
        $model = $this->entity;
        
        $builder = $model->where(function($q) use ($userId) {
            $q->where('executor_id', $userId)
            ->orWhere('creator_id', $userId);
        });
        
        if (!empty($query)) {
            $builder->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%");
            });
        }
        
        $builder->with(['project', 'executor', 'category'])
                ->orderBy('created_at', 'desc')
                ->limit($this->perSearchShow());

        return new \Laravel\Scout\Builder($model, $query, function() use ($builder) {
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