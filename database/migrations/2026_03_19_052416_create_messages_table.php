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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims', 'id')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items', 'id')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users', 'id')->onDelete('cascade');
            $table->text('message_body');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
