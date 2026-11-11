<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('notification_id');
            $table->string('channel');
            $table->string('provider');
            $table->string('provider_message_id')->nullable();
            $table->string('status')->default('queued');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('notification_id');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_receipts');
    }
};
