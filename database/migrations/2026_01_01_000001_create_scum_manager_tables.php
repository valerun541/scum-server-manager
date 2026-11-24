<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
        Schema::create('wargm_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value')->nullable();
            $table->timestamps();
        });
        Schema::create('wargm_cards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('shop_item_id');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
        Schema::create('wargm_card_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('wargm_cards')->cascadeOnDelete();
            $table->string('type');
            $table->json('data')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('wargm_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_id')->unique();
            $table->string('steam_id');
            $table->foreignId('card_id')->constrained('wargm_cards');
            $table->timestamp('delivered_at');
            $table->index('steam_id');
        });
        Schema::create('workflows', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->json('trigger');
            $table->json('nodes');
            $table->json('connections');
            $table->json('stats')->nullable();
            $table->timestamps();
        });
        Schema::create('log_events', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->text('message');
            $table->json('details')->nullable();
            $table->timestamps();
        });
        Schema::create('chat_events', function (Blueprint $table) {
            $table->id();
            $table->string('steam_id')->nullable();
            $table->string('player_name')->nullable();
            $table->string('channel')->nullable();
            $table->text('message');
            $table->timestamps();
        });
        Schema::create('rcon_command_logs', function (Blueprint $table) {
            $table->id();
            $table->text('command');
            $table->text('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('wargm_settings');
        Schema::dropIfExists('wargm_card_items');
        Schema::dropIfExists('wargm_deliveries');
        Schema::dropIfExists('wargm_cards');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('log_events');
        Schema::dropIfExists('chat_events');
        Schema::dropIfExists('rcon_command_logs');
    }
};
