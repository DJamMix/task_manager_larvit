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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('creator_id');
            $table->string('name');
            $table->json('observers_ids')->nullable();
            $table->bigInteger('executor_id')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('start_datetime')->nullable();
            $table->dateTime('end_datetime')->nullable();
            $table->float('cost_estimation')->nullable();
            $table->bigInteger('project_id');
            $table->string('status')->default('New');
            $table->boolean('pay_status')->default(false);
            $table->bigInteger('task_category_id')->nullable();
            $table->float('hours_spent')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
