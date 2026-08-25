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
        Schema::create('ppp_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_server_id')->constrained('mikrotik_servers')->cascadeOnDelete();
            $table->string('mikrotik_id', 64)->nullable();
            $table->string('name');
            $table->string('local_address', 100)->nullable();
            $table->string('remote_address', 100)->nullable();
            $table->string('dns_server', 255)->nullable();
            $table->string('rate_limit', 100)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['mikrotik_server_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppp_profiles');
    }
};
