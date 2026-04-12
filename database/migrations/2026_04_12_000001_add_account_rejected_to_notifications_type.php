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
        Schema::table('notifications', function (Blueprint $table) {
            // Drop and recreate the enum column with the new type
            $table->dropColumn('type');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type',['claim_submitted','claim_approved','claim_rejected','clarification_requested','clarification_responded','appeal_submitted','appeal_resolved','handover_requested','handover_confirmed','handover_rejected','item_collected','inactivity_reminder','verification_code','item_matched','account_approved','account_rejected','admin_message','general'])->after('is_read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type',['claim_submitted','claim_approved','claim_rejected','clarification_requested','clarification_responded','appeal_submitted','appeal_resolved','handover_requested','handover_confirmed','handover_rejected','item_collected','inactivity_reminder','verification_code','item_matched','account_approved','admin_message','general'])->after('is_read');
        });
    }
};
