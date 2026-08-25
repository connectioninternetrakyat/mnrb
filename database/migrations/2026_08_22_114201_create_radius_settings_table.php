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
        Schema::create('radius_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // FreeRADIUS server credentials
            $table->string('server_host');
            $table->unsignedSmallInteger('auth_port')->default(1812);
            $table->text('shared_secret')->nullable();
            $table->unsignedSmallInteger('timeout')->default(5);

            // RADIUS database credentials (MySQL / PostgreSQL)
            $table->string('db_driver', 20)->default('mysql');
            $table->string('db_host')->nullable();
            $table->unsignedInteger('db_port')->nullable();
            $table->string('db_database')->nullable();
            $table->string('db_username')->nullable();
            $table->text('db_password')->nullable();

            $table->boolean('is_connected')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radius_servers');
    }
};
