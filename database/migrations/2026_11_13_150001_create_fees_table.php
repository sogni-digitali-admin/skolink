<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fee definitions (one row per invoicable charge, e.g. "Term 1 School
     * Contribution"). Replaces the legacy private fee addon: table and column
     * names keep the legacy shape (name, term, end_date, school_id,
     * academic_year_id, standardLink_id) so old resources keep working, while
     * money is stored as integer cents (amount_cents, MUR).
     */
    public function up(): void
    {
        Schema::create('fees', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('school_id')->unsigned();
            $table->foreign('school_id')->references('id')->on('schools');
            $table->integer('academic_year_id')->unsigned();
            $table->foreign('academic_year_id')->references('id')->on('academic_years');
            // Trimestre the fee belongs to (academic_terms); null = whole year.
            $table->integer('academic_term_id')->unsigned()->nullable();
            $table->foreign('academic_term_id')->references('id')->on('academic_terms')->onDelete('set null');
            // null = school-wide fee, otherwise only that class/section.
            $table->integer('standardLink_id')->unsigned()->nullable();
            $table->foreign('standardLink_id')->references('id')->on('standards_link')->onDelete('set null');
            $table->string('name');
            // Legacy display label, e.g. "Term 1".
            $table->string('term')->nullable();
            $table->bigInteger('amount_cents')->unsigned();
            // Due date (legacy column name).
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fees');
    }
};
