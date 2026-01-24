<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acts', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('date');
            $table->string('customer');
            $table->string('executor');
            $table->text('info')->nullable();
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->integer('total_tasks')->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('act_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('act_id')->constrained()->onDelete('cascade');
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->decimal('hours', 8, 2)->nullable();
            $table->timestamp('included_at')->useCurrent();
            $table->timestamps();
            
            $table->unique(['act_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('act_task');
        Schema::dropIfExists('acts');
    }
};