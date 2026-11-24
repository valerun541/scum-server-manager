<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('');
            $table->string('ftp_protocol')->default('ftp');
            $table->string('ftp_host')->default('');
            $table->integer('ftp_port')->default(21);
            $table->string('ftp_username')->default('');
            $table->string('ftp_password')->default('');
            $table->string('ftp_log_path')->default('SCUM/Saved/SaveFiles/Logs');
            $table->string('ftp_scumdb_path')->default('SCUM/Saved/SaveFiles/SCUM.db');
            $table->string('rcon_host')->default('');
            $table->integer('rcon_port')->default(28015);
            $table->string('rcon_password')->default('');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
