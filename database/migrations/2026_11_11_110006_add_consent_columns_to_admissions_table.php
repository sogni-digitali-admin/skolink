<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DPA 2017 consent capture for the public admission form.
     *
     * `whatsapp_opted_in_at` is a deliberate hook for Phase 3 (WhatsApp
     * notifications): the channel legally requires opt-in, so the column is
     * created here alongside the other consents and will be consumed by the
     * Phase 3 messaging layer. Do NOT rename it.
     */
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->timestamp('data_processing_consented_at')->nullable()->after('remarks');
            $table->timestamp('notification_opted_in_at')->nullable()->after('data_processing_consented_at');
            // Reserved for Phase 3 (WhatsApp) — see note above.
            $table->timestamp('whatsapp_opted_in_at')->nullable()->after('notification_opted_in_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropColumn([
                'data_processing_consented_at',
                'notification_opted_in_at',
                'whatsapp_opted_in_at',
            ]);
        });
    }
};
