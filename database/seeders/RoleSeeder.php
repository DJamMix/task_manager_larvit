<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Orchid\Platform\Models\Role;
use Orchid\Platform\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создание ролей
        $admin = Role::firstOrCreate([
            'name' => 'Администратор',
            'slug' => 'admin',
        ]);
        $employee = Role::firstOrCreate([
            'name' => 'Сотрудник',
            'slug' => 'employee',
        ]);
        $client = Role::firstOrCreate([
            'name' => 'Клиент',
            'slug' => 'client',
        ]);
        $manager = Role::firstOrCreate([
            'name' => 'Менеджер',
            'slug' => 'manager',
        ]);
    }
}
