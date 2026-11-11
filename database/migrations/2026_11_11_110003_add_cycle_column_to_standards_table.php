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
        Schema::table('standards', function (Blueprint $table) {
            // Curriculum cycle tag, e.g. cycle_1 (Grades 1-3) / cycle_2 (Grades 4-6).
            $table->string('cycle')->nullable()->after('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('standards', function (Blueprint $table) {
            $table->dropColumn('cycle');
        });
    }
};
