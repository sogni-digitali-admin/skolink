<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('school_id')->unsigned();
            $table->foreign('school_id')->references('id')->on('schools');
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');
            // Links to the fee ledger row of the optional gegok12/fee module.
            // The FK is only added when that module's table exists.
            $table->integer('fee_payment_id')->unsigned()->nullable();
            if (Schema::hasTable('fee_payments')) {
                $table->foreign('fee_payment_id')->references('id')->on('fee_payments');
            }
            $table->bigInteger('amount_cents')->unsigned();
            $table->string('currency', 3)->default('MUR');
            $table->string('gateway');
            $table->string('method')->nullable();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('status')->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->integer('recorded_by')->unsigned()->nullable();
            $table->foreign('recorded_by')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'gateway']);
            $table->index(['school_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
