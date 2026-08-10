<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('color', 16)->default('#64748b');
            $table->string('category', 32)->default('in_progress'); // todo | in_progress | done
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_final')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_status_id')->constrained('workflow_statuses')->cascadeOnDelete();
            $table->foreignId('to_status_id')->constrained('workflow_statuses')->cascadeOnDelete();
            $table->string('name', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['from_status_id', 'to_status_id']);
        });

        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('name', 160);
            $table->string('type', 32)->default('kanban'); // kanban | scrum
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('workflow_statuses')->cascadeOnDelete();
            $table->string('name', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('wip_limit')->nullable();
            $table->timestamps();

            $table->unique(['board_id', 'status_id']);
        });

        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('board_id')->nullable()->constrained('boards')->nullOnDelete();
            $table->string('name', 160);
            $table->text('goal')->nullable();
            $table->string('status', 32)->default('planned'); // planned | active | closed
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable()->after('status')->constrained('workflow_statuses')->nullOnDelete();
            $table->foreignId('sprint_id')->nullable()->after('project_id')->constrained('sprints')->nullOnDelete();
            $table->foreignId('board_id')->nullable()->after('sprint_id')->constrained('boards')->nullOnDelete();
            $table->unsignedInteger('board_sort')->nullable()->after('board_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_id');
            $table->dropConstrainedForeignId('sprint_id');
            $table->dropConstrainedForeignId('board_id');
            $table->dropColumn('board_sort');
        });

        Schema::dropIfExists('sprints');
        Schema::dropIfExists('board_columns');
        Schema::dropIfExists('boards');
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_statuses');
    }
};
