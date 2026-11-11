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
        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('is_core')->default(true)->after('type');
            // national_final | national_modular | school_based
            $table->string('assessment_type')->nullable()->after('is_core');
            $table->integer('grading_scale_id')->unsigned()->nullable()->after('assessment_type');
            $table->foreign('grading_scale_id')->references('id')->on('grading_scales')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['grading_scale_id']);
            $table->dropColumn(['is_core', 'assessment_type', 'grading_scale_id']);
        });
    }
};
