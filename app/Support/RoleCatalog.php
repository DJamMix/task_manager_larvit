<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Каталог ролей и матрица доступов.
 * Seeder обновляет роли по slug (firstOrCreate) — не удаляет пользователей и role_users.
 */
final class RoleCatalog
{
    public const CLIENT_SLUGS = ['client', 'client_employer', 'client_contact'];

    public const STAFF_SLUGS = ['admin', 'pm', 'manager', 'employee'];

    /** Кто может быть в чатах (сотрудники + клиентские контакты) */
    public const CHAT_MEMBER_SLUGS = ['admin', 'pm', 'manager', 'employee', 'client', 'client_employer', 'client_contact'];

    /**
     * @return array<string, array{name: string, description: string, permissions: list<string>}>
     */
    public static function definitions(): array
    {
        $allSystem = [
            'platform.systems.roles',
            'platform.systems.users',
            'platform.systems.attachment',
            'platform.systems.tasks',
            'platform.systems.projects',
            'platform.systems.task_categories',
            'platform.systems.my_tasks',
            'platform.systems.chats',
            'platform.systems.chats.create',
            'platform.systems.chats.clients',
            'platform.systems.bots',
            'platform.systems.client.projects',
            'platform.systems.client.project.tasks',
            'platform.systems.client.project.tasks.view',
            'platform.systems.contact.tasks',
            'platform.systems.acts',
        ];

        $pm = [
            'platform.systems.attachment',
            'platform.systems.tasks',
            'platform.systems.projects',
            'platform.systems.task_categories',
            'platform.systems.my_tasks',
            'platform.systems.chats',
            'platform.systems.chats.create',
            'platform.systems.chats.clients',
            'platform.systems.acts',
            'platform.systems.users',
        ];

        $employee = [
            'platform.systems.attachment',
            'platform.systems.my_tasks',
            'platform.systems.chats',
        ];

        $client = [
            'platform.systems.attachment',
            'platform.systems.chats',
            'platform.systems.client.projects',
            'platform.systems.client.project.tasks',
            'platform.systems.client.project.tasks.view',
        ];

        $clientContact = [
            'platform.systems.attachment',
            'platform.systems.chats',
            'platform.systems.contact.tasks',
        ];

        return [
            'admin' => [
                'name' => 'Администратор',
                'description' => 'Полный доступ: пользователи, роли, задачи, проекты, акты',
                'permissions' => $allSystem,
            ],
            'pm' => [
                'name' => 'Проектный менеджер',
                'description' => 'Ведение проектов, задач, актов и команды. Без управления ролями',
                'permissions' => $pm,
            ],
            'manager' => [
                'name' => 'Менеджер',
                'description' => 'То же, что PM (совместимость со старой ролью manager)',
                'permissions' => $pm,
            ],
            'employee' => [
                'name' => 'Сотрудник',
                'description' => 'Свои задачи, входящие, учёт времени, комментарии, чаты',
                'permissions' => $employee,
            ],
            'client' => [
                'name' => 'Клиент',
                'description' => 'Проекты, задачи и чаты с командой',
                'permissions' => $client,
            ],
            'client_employer' => [
                'name' => 'Заказчик',
                'description' => 'Клиентский доступ для представителя компании-заказчика',
                'permissions' => $client,
            ],
            'client_contact' => [
                'name' => 'Контакт клиента',
                'description' => 'Чаты + просмотр задач, где добавлен наблюдателем (отдельный раздел)',
                'permissions' => $clientContact,
            ],
        ];
    }

    public static function description(string $slug): string
    {
        return self::definitions()[$slug]['description'] ?? '';
    }

    public static function displayName(string $slug): string
    {
        return self::definitions()[$slug]['name'] ?? $slug;
    }

    public static function isClientRole(string $slug): bool
    {
        return in_array($slug, self::CLIENT_SLUGS, true);
    }

    /**
     * @param  iterable<int|string>  $roleIdsOrSlugs
     * @return list<string>
     */
    public static function resolveSlugs(iterable $roleIdsOrSlugs): array
    {
        $values = collect($roleIdsOrSlugs)->filter()->values();

        if ($values->isEmpty()) {
            return [];
        }

        // Если пришли ID — резолвим в slug; если уже slug — оставляем
        $numeric = $values->filter(fn ($v) => is_numeric($v));
        $slugs = $values->reject(fn ($v) => is_numeric($v))->map(fn ($v) => (string) $v)->all();

        if ($numeric->isNotEmpty()) {
            $fromIds = \Orchid\Platform\Models\Role::query()
                ->whereIn('id', $numeric->all())
                ->pluck('slug')
                ->all();
            $slugs = array_merge($slugs, $fromIds);
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Должности для удобного выбора (можно ввести и свою).
     *
     * @return array<string, string>
     */
    public static function positionOptions(): array
    {
        return [
            'Backend' => 'Backend',
            'Frontend' => 'Frontend',
            'Fullstack' => 'Fullstack',
            'Designer' => 'Дизайнер',
            'QA' => 'QA / Тестировщик',
            'DevOps' => 'DevOps',
            'PM' => 'Project Manager',
            'Analyst' => 'Аналитик',
            'Mobile' => 'Mobile',
            'Lead' => 'Team Lead',
            'Director' => 'Директор / Владелец',
        ];
    }
}
