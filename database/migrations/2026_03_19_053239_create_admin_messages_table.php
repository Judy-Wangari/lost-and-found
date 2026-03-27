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
        Schema::create('admin_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->nullable()->constrained('claims', 'id')->onDelete('cascade');
            $table->foreignId('appeal_id')->nullable()->constrained('appeals', 'id')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users', 'id')->onDelete('cascade');
            $table->text('message');
            $table->enum('type',['claim','appeal','direct','unresponsive'])->default('direct');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_messages');
    }
};
