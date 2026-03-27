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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posted_by')->constrained('users', 'id')->onDelete('cascade');
            $table->enum('category',['eyewear', 'electronics', 'stationery', 'clothing_and_accessories', 'documents_and_cards','bags_and_luggage','keys','other']);
            $table->string('photo_path');
            $table->enum('status',['listed','under_review','awaiting_collection','clarification_requested','flagged_to_security','unresponsive','collected'])->default('listed');
            $table->foreignId('claimed_by')->nullable()->constrained('users', 'id')->onDelete('set null');
            $table->string('verification_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
