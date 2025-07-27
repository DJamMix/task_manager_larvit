<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tracking_times', function (Blueprint $table) {
            $table->ulid('id')->primary(); // Используем ULID вместо стандартного ID
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            
            // Затраченное время (в часах)
            $table->decimal('hours_spent', 8, 2)->comment('Количество затраченных часов');
            
            // Комментарий о проделанной работе
            $table->text('work_description')->comment('Подробное описание выполненной работы');
            
            // Дополнительные мета-данные
            $table->date('work_date')->index()->comment('Дата выполнения работы');
            $table->foreignId('user_id')->constrained()->comment('Кто добавил запись');
            
            $table->timestamps();
            
            // Индексы для оптимизации запросов
            $table->index(['task_id', 'work_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_times');
    }
};
