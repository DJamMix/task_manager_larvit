<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\AppDashboardMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Orchid\Support\Color;

/**
 * Внутренние уведомления в колокольчик Orchid (без Telegram).
 */
class DashboardNotifier
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function send(
        User $user,
        string $title,
        string $message,
        string $actionUrl,
        Color $type = Color::INFO,
        array $meta = []
    ): void {
        try {
            $body = Str::limit(strip_tags($message), 240);
            if ($type instanceof \BackedEnum) {
                $typeValue = strtolower((string) $type->value);
            } elseif ($type instanceof \UnitEnum) {
                $typeValue = strtolower($type->name);
            } else {
                $typeValue = strtolower(trim((string) $type));
            }
            if (!in_array($typeValue, ['info', 'success', 'warning', 'error', 'danger', 'primary', 'secondary', 'light', 'dark'], true)) {
                $typeValue = 'info';
            }
            if ($typeValue === 'danger') {
                $typeValue = 'error';
            }

            $user->notify(new AppDashboardMessage(
                $title,
                $body,
                $actionUrl,
                $typeValue,
                $meta
            ));
            app(WebPushService::class)->send(
                $user,
                $title,
                $body,
                $actionUrl
            );
        } catch (\Throwable) {
            // Не ломаем основное действие из‑за уведомления
        }
    }

    /**
     * Удаляет уведомления колокольчика, привязанные к сообщениям чата.
     *
     * @param  list<int>  $messageIds
     */
    public function deleteForChatMessages(array $messageIds): void
    {
        $ids = collect($messageIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        try {
            $query = DB::table('notifications')->where(function ($outer) use ($ids) {
                $outer->where('type', AppDashboardMessage::class)
                    ->orWhere('type', 'like', '%DashboardMessage%');

                $outer->where(function ($q) use ($ids) {
                    foreach ($ids as $messageId) {
                        $q->orWhere('data', 'like', '%"message_id":' . $messageId . '%')
                            ->orWhere('data', 'like', '%"message_id": ' . $messageId . '%')
                            ->orWhere('data', 'like', '%msg=' . $messageId . '%');
                    }
                });
            });

            $query->delete();
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @param  iterable<int|User>  $recipients
     * @param  array<string, mixed>  $meta
     */
    public function sendMany(
        iterable $recipients,
        string $title,
        string $message,
        string $actionUrl,
        Color $type = Color::INFO,
        ?int $exceptUserId = null,
        array $meta = []
    ): void {
        $users = $this->resolveUsers($recipients, $exceptUserId);

        foreach ($users as $user) {
            $this->send($user, $title, $message, $actionUrl, $type, $meta);
        }
    }

    public function taskUrlFor(User $user, Task $task): string
    {
        if ($user->hasAccess('platform.systems.contact.tasks') && $task->canView((int) $user->id)) {
            return URL::route('platform.systems.contact.tasks.view', $task->id);
        }

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
