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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique();
            $table->foreignId('psb_ticket_id')->constrained('psb_tickets')->cascadeOnDelete();
            $table->string('period', 7);
            $table->unsignedBigInteger('monthly_price');
            $table->unsignedBigInteger('dpp');
            $table->unsignedBigInteger('ppn');
            $table->unsignedBigInteger('total');
            $table->string('status', 30)->default('menunggu_pembayaran')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['psb_ticket_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
