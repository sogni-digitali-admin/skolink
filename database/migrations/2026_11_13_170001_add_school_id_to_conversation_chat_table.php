<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * All chat conversations are school-scoped (Skolink product rule).
     * conversation_chat predates that rule, so the scope is recorded
     * explicitly here; existing rows stay NULL and are backfilled on
     * first message through the API.
     */
    public function up(): void
    {
        Schema::table('conversation_chat', function (Blueprint $table) {
            $table->bigInteger('school_id')->unsigned()->nullable()->after('id')->index();
            $table->foreign('school_id')->references('id')->on('schools');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_chat', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });
    }
};
