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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('psb_ticket_id')->constrained('psb_tickets')->cascadeOnDelete();
            $table->string('payment_method', 20);
            $table->string('bank', 20)->nullable();
            $table->string('va_number', 32)->nullable();
            $table->unsignedBigInteger('monthly_price');
            $table->unsignedSmallInteger('period_days');
            $table->unsignedSmallInteger('active_days');
            $table->unsignedBigInteger('prorata');
            $table->unsignedBigInteger('otc_fee');
            $table->unsignedTinyInteger('promo_percent');
            $table->unsignedBigInteger('discount');
            $table->unsignedBigInteger('dpp');
            $table->unsignedBigInteger('ppn');
            $table->unsignedBigInteger('total_exact');
            $table->unsignedBigInteger('cash_total');
            $table->string('status', 30)->default('menunggu_pembayaran')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique('psb_ticket_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
