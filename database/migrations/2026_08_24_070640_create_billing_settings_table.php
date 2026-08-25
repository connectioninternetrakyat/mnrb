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
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('prorate_cut_day')->default(10);
            $table->unsignedTinyInteger('invoice_day')->default(11);
            $table->unsignedTinyInteger('isolir_day')->nullable();
            $table->string('isolir_hour', 5)->default('23:59');
            $table->string('isolir_profile', 100)->default('Isolir');
            $table->decimal('ppn_percent', 5, 2)->default(11);
            $table->unsignedTinyInteger('promo_percent')->default(0);
            $table->boolean('remind_h3')->default(true);
            $table->boolean('remind_h2')->default(true);
            $table->boolean('remind_h1')->default(true);
            $table->string('last_generated_period', 7)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
