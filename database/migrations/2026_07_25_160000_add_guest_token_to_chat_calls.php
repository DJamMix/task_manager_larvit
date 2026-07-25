<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_calls', function (Blueprint $table) {
            $table->string('guest_token', 64)->nullable()->unique()->after('e2ee_key');
        });
    }

    public function down(): void
    {
        Schema::table('chat_calls', function (Blueprint $table) {
            $table->dropUnique(['guest_token']);
            $table->dropColumn('guest_token');
        });
    }
};
