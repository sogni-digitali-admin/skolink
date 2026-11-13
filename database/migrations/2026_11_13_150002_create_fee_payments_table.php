<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-student fee assignments (invoices). Keeps the legacy table and
     * column names (fee_id, user_id, paid_on, notify_parent, status) so the
     * Phase 4 defensive sync in App\Payments\PaymentService::syncFeeLedger
     * connects without modification: it sets paid_on + status = 1 on the
     * linked row when a payment is marked paid.
     *
     * status is the legacy integer flag: 0 = pending, 1 = paid, 2 = partial.
     * The API exposes the string form (pending|partial|paid).
     */
    public function up(): void
    {
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('fee_id')->unsigned();
            $table->foreign('fee_id')->references('id')->on('fees');
            // Student the invoice is assigned to.
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');
            // Denormalised for scoping/reporting without joining fees.
            $table->bigInteger('school_id')->unsigned();
            $table->foreign('school_id')->references('id')->on('schools');
            $table->bigInteger('amount_due_cents')->unsigned();
            $table->bigInteger('amount_paid_cents')->unsigned()->default(0);
            // Set (by PaymentService::syncFeeLedger or manually) when settled.
            $table->date('paid_on')->nullable();
            $table->boolean('notify_parent')->nullable();
            $table->unsignedTinyInteger('status')->default(0)->index();
            // Idempotency key for parent-initiated payments.
            $table->string('client_reference')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['school_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
