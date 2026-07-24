<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('projects', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
        });

        Schema::table('acts', function (Blueprint $table) {
            if (!Schema::hasColumn('acts', 'project_id')) {
                $table->foreignId('project_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('projects')
                    ->nullOnDelete();
            }
        });

        if (!Schema::hasTable('project_members')) {
            Schema::create('project_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['project_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');

        Schema::table('acts', function (Blueprint $table) {
            if (Schema::hasColumn('acts', 'project_id')) {
                $table->dropConstrainedForeignId('project_id');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('projects', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
