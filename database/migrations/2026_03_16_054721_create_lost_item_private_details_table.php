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
        Schema::create('lost_item_private_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lost_items')->constrained('lost_items', 'id')->onDelete('cascade');
             $table->string('brand_model_or_logo')->nullable();
            $table->text('what_was_inside_or_attached')->nullable();
            $table->text('hidden_or_internal_details')->nullable();
            $table->text('extra_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lost_item_private_details');
    }
};
