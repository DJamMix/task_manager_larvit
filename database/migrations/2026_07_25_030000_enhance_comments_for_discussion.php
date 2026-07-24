<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            if (!Schema::hasColumn('comments', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('task_id')
                    ->constrained('comments')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('comments', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('plain_text');
            }

            if (!Schema::hasColumn('comments', 'mentioned_user_ids')) {
                $table->json('mentioned_user_ids')->nullable()->after('is_system');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            if (Schema::hasColumn('comments', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
            if (Schema::hasColumn('comments', 'is_system')) {
                $table->dropColumn('is_system');
            }
            if (Schema::hasColumn('comments', 'mentioned_user_ids')) {
                $table->dropColumn('mentioned_user_ids');
            }
        });
    }
};
