<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicle_insurances')) {
            Schema::create('vehicle_insurances', function (Blueprint $table) {
                $table->id();
                $table->string('steam_id', 32)->index();
                $table->string('player_name', 128)->nullable();
                $table->string('purchase_id', 64)->nullable()->index();
                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->string('vehicle_asset', 128)->nullable();
                $table->string('vehicle_alias', 128)->nullable();
                $table->string('status', 32)->default('pending')->index(); // pending, active, destroyed, claimed, expired
                $table->unsignedInteger('claims_total')->default(1);
                $table->unsignedInteger('claims_used')->default(0);
                $table->timestamp('last_claim_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_insurances');
    }
};
