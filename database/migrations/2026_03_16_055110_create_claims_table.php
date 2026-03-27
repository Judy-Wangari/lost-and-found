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
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claimed_by')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items', 'id')->onDelete('cascade');
            $table->foreignId('lost_item_id')->nullable()->constrained('lost_items', 'id')->onDelete('set null');
            $table->string('brand_model_or_logo')->nullable();
            $table->text('what_was_inside_or_attached')->nullable();
            $table->text('hidden_or_internal_details')->nullable();
            $table->text('extra_notes')->nullable();
            $table->enum('status',['pending_review','clarification_requested','approved','rejected'])->default('pending_review');
            $table->integer('pending_review_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
