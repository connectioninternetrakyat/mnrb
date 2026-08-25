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
        Schema::create('psb_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('progress_note')->nullable();
            $table->text('finish_note')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->boolean('check_pppoe_account')->default(false);
            $table->boolean('check_customer_active')->default(false);
            $table->boolean('check_vlan_tr069')->default(false);
            $table->string('status', 30)->default('terjadwal')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('isolated_at')->nullable();
            $table->string('isolated_profile', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psb_tickets');
    }
};
