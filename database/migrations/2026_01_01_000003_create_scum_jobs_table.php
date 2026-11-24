<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scum_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // chat_command | event
            $table->json('payload')->nullable();
            $table->string('status')->default('pending')->index(); // pending | processing | done | error
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scum_jobs');
    }
};
