<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignId('started_by')->constrained('users')->cascadeOnDelete();
            $table->string('room_name', 120)->unique();
            $table->string('status', 20)->default('ringing'); // ringing|active|ended
            $table->boolean('video_enabled')->default(true);
            $table->string('e2ee_key', 64)->nullable(); // shared room key for LiveKit E2EE (HTTPS only)
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['chat_id', 'status']);
        });

        Schema::create('chat_call_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_call_id')->constrained('chat_calls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('invited'); // invited|joined|left|declined
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_call_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_call_participants');
        Schema::dropIfExists('chat_calls');
    }
};
