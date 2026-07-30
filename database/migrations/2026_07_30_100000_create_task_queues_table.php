<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_queues', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('next_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('queue_id')->nullable()->after('project_id')->constrained('task_queues')->nullOnDelete();
            $table->unsignedInteger('queue_number')->nullable()->after('queue_id');
            $table->unique(['queue_id', 'queue_number']);
        });

        DB::table('task_queues')->insert([
            [
                'key' => 'PHP',
                'name' => 'Backend / PHP',
                'description' => 'Задачи по бэкенду',
                'next_number' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'FRONTEND',
                'name' => 'Frontend',
                'description' => 'Задачи по фронтенду',
                'next_number' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'DEVOPS',
                'name' => 'DevOps',
                'description' => 'Инфраструктура и деплой',
                'next_number' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['queue_id', 'queue_number']);
            $table->dropConstrainedForeignId('queue_id');
            $table->dropColumn('queue_number');
        });
        Schema::dropIfExists('task_queues');
    }
};
