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
        Schema::create('pelanggans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('whatsapp', 20);
            $table->string('address', 500);
            $table->foreignId('area_code_id')->constrained('area_codes')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('ppp_secret_id')->nullable()->constrained('ppp_secrets')->nullOnDelete();
            $table->foreignId('psb_ticket_id')->nullable()->constrained('psb_tickets')->nullOnDelete();
            $table->string('status', 20)->default('aktif')->index();
            $table->date('joined_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('psb_ticket_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pelanggans');
    }
};
