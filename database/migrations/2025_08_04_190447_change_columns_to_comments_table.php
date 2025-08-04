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
        Schema::table('comments', function (Blueprint $table) {
            // 1. Сначала добавляем новое временное поле
            $table->json('text_temp')->after('text');
            
            // 2. Добавляем поле для plain text
            $table->text('plain_text')->after('text_temp');
        });

        // 3. Переносим данные (выполнится в транзакции)
        \DB::transaction(function () {
            \DB::table('comments')->orderBy('id')->chunk(100, function ($comments) {
                foreach ($comments as $comment) {
                    \DB::table('comments')
                        ->where('id', $comment->id)
                        ->update([
                            'text_temp' => json_encode(['ops' => [['insert' => $comment->text]]]),
                            'plain_text' => $comment->text
                        ]);
                }
            });
        });

        Schema::table('comments', function (Blueprint $table) {
            // 4. Удаляем старое поле
            $table->dropColumn('text');
            
            // 5. Переименовываем временное поле
            $table->renameColumn('text_temp', 'text');
            
            // 6. Добавляем fulltext индекс
            $table->fullText('plain_text');
        });
    }

    public function down(): void
    {
        // 1. Полностью очищаем таблицу
        \DB::table('comments')->truncate();

        // 2. Удаляем все изменения схемы безопасным способом
        Schema::table('comments', function (Blueprint $table) {
            // Для MySQL сначала удаляем индекс через прямое SQL
            if (\DB::connection()->getDriverName() === 'mysql') {
                try {
                    \DB::statement('ALTER TABLE comments DROP INDEX comments_plain_text_fulltext');
                } catch (\Exception $e) {
                    // Игнорируем ошибку, если индекс не существует
                }
            }

            // Удаляем добавленные колонки (с проверкой существования)
            $columns = Schema::getColumnListing('comments');
            
            if (in_array('text_temp', $columns)) {
                $table->dropColumn('text_temp');
            }
            
            if (in_array('plain_text', $columns)) {
                $table->dropColumn('plain_text');
            }

            // Восстанавливаем оригинальную колонку text
            if (!in_array('text', $columns)) {
                $table->text('text')->nullable();
            }
        });
    }
};
