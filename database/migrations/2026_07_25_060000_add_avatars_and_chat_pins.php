<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path', 500)->nullable()->after('position');
            }
        });

        Schema::table('chats', function (Blueprint $table) {
            if (!Schema::hasColumn('chats', 'avatar_path')) {
                $table->string('avatar_path', 500)->nullable()->after('description');
            }
        });

        Schema::table('chat_user', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_user', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('last_read_at');
            }
            if (!Schema::hasColumn('chat_user', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('is_pinned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
        });

        Schema::table('chats', function (Blueprint $table) {
            if (Schema::hasColumn('chats', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
        });

        Schema::table('chat_user', function (Blueprint $table) {
            if (Schema::hasColumn('chat_user', 'pinned_at')) {
                $table->dropColumn('pinned_at');
            }
            if (Schema::hasColumn('chat_user', 'is_pinned')) {
                $table->dropColumn('is_pinned');
            }
        });
    }
};
