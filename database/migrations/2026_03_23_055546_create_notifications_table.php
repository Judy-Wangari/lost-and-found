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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->text('message_body');
            $table->boolean('is_read')->default(false);
            $table->enum('type',['claim_submitted','claim_approved','claim_rejected','clarification_requested','clarification_responded','appeal_submitted','appeal_resolved','handover_requested','handover_confirmed','handover_rejected','item_collected','inactivity_reminder','verification_code','item_matched','account_approved','admin_message','general']);
            $table->string('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
