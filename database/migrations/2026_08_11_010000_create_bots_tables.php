<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_bot')) {
                $table->boolean('is_bot')->default(false)->after('email');
            }
        });

        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('username', 64)->unique();
            $table->text('description')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->string('token_hint', 24)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('can_join_groups')->default(true);
            $table->boolean('can_read_messages')->default(true);
            $table->string('webhook_url', 500)->nullable();
            $table->string('webhook_secret', 128)->nullable();
            $table->unsignedInteger('webhook_error_count')->default(0);
            $table->timestamp('webhook_last_error_at')->nullable();
            $table->text('webhook_last_error')->nullable();
            $table->json('commands')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bot_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->cascadeOnDelete();
            $table->string('update_type', 64)->default('message');
            $table->json('payload');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['bot_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_updates');
        Schema::dropIfExists('bots');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_bot')) {
                $table->dropColumn('is_bot');
            }
        });
    }
};
