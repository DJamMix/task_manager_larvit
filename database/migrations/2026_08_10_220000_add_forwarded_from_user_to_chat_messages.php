<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('forwarded_from_user_id')
                ->nullable()
                ->after('forwarded_from_chat_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Бэкап: для уже пересланных — автор исходного сообщения
        if (Schema::hasColumn('chat_messages', 'forwarded_from_message_id')) {
            DB::statement('
                UPDATE chat_messages AS cm
                INNER JOIN chat_messages AS src ON src.id = cm.forwarded_from_message_id
                SET cm.forwarded_from_user_id = COALESCE(src.forwarded_from_user_id, src.user_id)
                WHERE cm.forwarded_from_message_id IS NOT NULL
                  AND cm.forwarded_from_user_id IS NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forwarded_from_user_id');
        });
    }
};
