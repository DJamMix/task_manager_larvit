<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('forwarded_from_message_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('chat_messages')
                ->nullOnDelete();
            $table->foreignId('forwarded_from_chat_id')
                ->nullable()
                ->after('forwarded_from_message_id')
                ->constrained('chats')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forwarded_from_message_id');
            $table->dropConstrainedForeignId('forwarded_from_chat_id');
        });
    }
};
