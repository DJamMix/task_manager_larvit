<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Orchid\Platform\Notifications\DashboardMessage;
use Orchid\Support\Color;

/**
 * Внутренние уведомления в колокольчик Orchid (без Telegram).
 */
class DashboardNotifier
{
    public function send(
        User $user,
        string $title,
        string $message,
        string $actionUrl,
        Color $type = Color::INFO
    ): void {
        try {
            $user->notify(
                DashboardMessage::make()
                    ->title($title)
                    ->message(Str::limit(strip_tags($message), 240))
                    ->action($actionUrl)
                    ->type($type)
            );
        } catch (\Throwable) {
            // Не ломаем основное действие из‑за уведомления
        }
    }

    /**
     * @param  iterable<int|User>  $recipients
     */
    public function sendMany(
        iterable $recipients,
        string $title,
        string $message,
        string $actionUrl,
        Color $type = Color::INFO,
        ?int $exceptUserId = null
    ): void {
        $users = $this->resolveUsers($recipients, $exceptUserId);

        foreach ($users as $user) {
            $this->send($user, $title, $message, $actionUrl, $type);
        }
    }

    public function taskUrlFor(User $user, Task $task): string
    {
        if ($user->hasAccess('platform.systems.my_tasks')
            && ((int) $task->executor_id === (int) $user->id || $task->isObserver((int) $user->id))) {
            return URL::route('platform.systems.my_tasks.view', $task->id);
        }

        if ($user->hasAccess('platform.systems.client.project.tasks.view') && $task->project_id) {
            return URL::route('platform.systems.client.project.tasks.view', [
                'project' => $task->project_id,
                'task' => $task->id,
            ]);
        }

        if ($user->hasAccess('platform.systems.tasks')) {
            return URL::route('platform.systems.tasks.edit', $task->id);
        }

        if ($user->hasAccess('platform.systems.my_tasks')) {
            return URL::route('platform.systems.my_tasks.view', $task->id);
        }

        return URL::route('platform.welcome');
    }

    /**
     * @param  iterable<int|User>  $recipients
     * @return Collection<int, User>
     */
    private function resolveUsers(iterable $recipients, ?int $exceptUserId): Collection
    {
        $ids = collect();
        $models = collect();

        foreach ($recipients as $item) {
            if ($item instanceof User) {
                $models->put($item->id, $item);
                continue;
            }
            if ($item) {
                $ids->push((int) $item);
            }
        }

        if ($ids->isNotEmpty()) {
            User::query()->whereIn('id', $ids->unique())->get()
                ->each(fn (User $u) => $models->put($u->id, $u));
        }

        return $models
            ->when($exceptUserId, fn ($c) => $c->reject(fn (User $u) => (int) $u->id === (int) $exceptUserId))
            ->values();
    }
}
