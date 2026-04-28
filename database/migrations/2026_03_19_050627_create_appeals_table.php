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
        Schema::create('appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims', 'id')->onDelete('cascade');
            $table->foreignId('raised_by')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items', 'id')->onDelete('cascade');
            $table->enum('status',['pending','under_review','resolved','rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appeals');
    }
};
