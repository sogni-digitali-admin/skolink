<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores one result per modular assessment session. For the PSAC modular
     * subjects (Science, History & Geography) a pupil sits a module at the
     * end of Grade 5 and another at the end of Term 2 of Grade 6; each
     * result is banked (is_banked) and the final grade is computed by
     * cumulating the banked sessions (see ModularAssessmentService).
     */
    public function up(): void
    {
        Schema::create('modular_assessment_results', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('school_id')->unsigned();
            $table->foreign('school_id')->references('id')->on('schools');
            $table->integer('academic_year_id')->unsigned();
            $table->foreign('academic_year_id')->references('id')->on('academic_years');
            $table->integer('user_id')->unsigned(); // student
            $table->foreign('user_id')->references('id')->on('users');
            $table->integer('subject_id')->unsigned();
            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->integer('standard_id')->unsigned(); // grade level when assessed
            $table->foreign('standard_id')->references('id')->on('standards');
            $table->unsignedTinyInteger('term_sequence'); // 1-3
            $table->decimal('score', 5, 2)->nullable();    // percentage
            $table->string('grade_label')->nullable();     // e.g. PSAC 1-6, or Proficient
            $table->integer('grading_scale_id')->unsigned()->nullable();
            $table->foreign('grading_scale_id')->references('id')->on('grading_scales')->onDelete('set null');
            $table->boolean('is_banked')->default(false);
            $table->timestamp('banked_at')->nullable();
            $table->date('assessed_on')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'subject_id'], 'mar_student_subject_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modular_assessment_results');
    }
};
