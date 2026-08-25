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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('whatsapp', 20);
            $table->string('address', 500);
            $table->foreignId('area_code_id')->constrained('area_codes')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('psb_ticket_id')->nullable()->after('package_id');
            $table->foreignId('ppp_secret_id')->nullable()->constrained('ppp_secrets')->nullOnDelete();
            $table->string('status', 30)->default('aktif')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
