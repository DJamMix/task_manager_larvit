<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Безопасная аддитивная миграция: только nullable-колонка.
 * Не трогает существующие строки и не удаляет данные.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'position')) {
                $table->string('position', 100)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'position')) {
                $table->dropColumn('position');
            }
        });
    }
};
