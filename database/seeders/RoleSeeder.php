<?php

namespace Database\Seeders;

use App\Support\RoleCatalog;
use Illuminate\Database\Seeder;
use Orchid\Platform\Models\Role;

/**
 * Безопасно синхронизирует роли и их permissions.
 * - Не удаляет роли, пользователей и связи role_users
 * - Создаёт отсутствующие роли (pm, client_employer и т.д.)
 * - Обновляет name + permissions по slug
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleCatalog::definitions() as $slug => $definition) {
            $role = Role::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name']]
            );

            $role->name = $definition['name'];
            $role->permissions = collect($definition['permissions'])
                ->mapWithKeys(fn (string $permission) => [$permission => true])
                ->all();
            $role->save();

            $this->command?->info("Роль [{$slug}] → {$role->name} (" . count($definition['permissions']) . ' прав)');
        }

        $this->command?->warn('Пользователи и их привязки к ролям не изменялись.');
    }
}
