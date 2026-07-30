<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('related_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('relation', 32)->default('relates'); // relates|blocks|blocked_by|parent|subtask
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'related_task_id', 'relation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_links');
    }
};
