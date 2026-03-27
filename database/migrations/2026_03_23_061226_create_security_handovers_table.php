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
        Schema::create('security_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items', 'id')->onDelete('cascade');
            $table->foreignId('claim_id')->constrained('claims', 'id')->onDelete('cascade');
            $table->foreignId('handed_over_by')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('receiver_id')->nullable()->constrained('users', 'id')->onDelete('set null');
            $table->foreignId('owner_id')->constrained('users', 'id')->onDelete('cascade');
            $table->enum('status',['pending_confirmation','confirmed','rejected','collected'])->default('pending_confirmation');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_handovers');
    }
};
