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
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('school_id')->unsigned()->nullable();
            $table->foreign('school_id')->references('id')->on('schools');
            $table->string('slug');
            $table->string('name');
            $table->string('type')->default('numeric'); // numeric | qualitative
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'slug']);
        });

        Schema::create('grading_scale_grades', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('grading_scale_id')->unsigned();
            $table->foreign('grading_scale_id')->references('id')->on('grading_scales')->onDelete('cascade');
            $table->string('label');
            $table->decimal('min_mark', 5, 2)->nullable(); // null for qualitative grades
            $table->decimal('max_mark', 5, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_scale_grades');
        Schema::dropIfExists('grading_scales');
    }
};
