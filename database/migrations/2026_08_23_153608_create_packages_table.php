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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('ppp_profile_id')->constrained('ppp_profiles')->cascadeOnDelete();
            $table->unsignedBigInteger('price')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique('ppp_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
