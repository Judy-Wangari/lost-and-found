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
        Schema::create('lost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posted_by')->constrained('users', 'id')->onDelete('cascade');
            $table->enum('category',['eyewear', 'electronics', 'stationery', 'clothing_and_accessories', 'documents_and_cards','bags_and_luggage','keys','other']);
            $table->text('general_description');
            $table->string('photo_path')->nullable();
            $table->enum('status',['searching','found','expired','closed'])->default('searching');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lost_items');
    }
};
