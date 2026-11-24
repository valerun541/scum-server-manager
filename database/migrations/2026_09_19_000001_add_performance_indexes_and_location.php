<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Оптимизация таблицы scum_jobs (составной индекс для claim)
        Schema::table('scum_jobs', function (Blueprint $table) {
            $table->index(['status', 'id'], 'scum_jobs_status_id_idx');
        });

        // 2. Индексы для chat_events
        Schema::table('chat_events', function (Blueprint $table) {
            $table->index('created_at', 'chat_events_created_at_idx');
            $table->index('player_name', 'chat_events_player_name_idx');
        });

        // 3. Таблица wargm_deliveries: сохранение координат и составной индекс
        Schema::table('wargm_deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('wargm_deliveries', 'location')) {
                $table->string('location')->nullable()->after('delivered_at');
            }
            if (! Schema::hasColumn('wargm_deliveries', 'player_name')) {
                $table->string('player_name')->nullable()->after('steam_id');
            }
            if (! Schema::hasColumn('wargm_deliveries', 'items_summary')) {
                $table->text('items_summary')->nullable()->after('location');
            }
            if (! Schema::hasColumn('wargm_deliveries', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
            $table->index(['steam_id', 'card_id'], 'wargm_deliveries_steam_card_idx');
        });
    }

    public function down(): void
    {
        Schema::table('scum_jobs', function (Blueprint $table) {
            $table->dropIndex('scum_jobs_status_id_idx');
        });

        Schema::table('chat_events', function (Blueprint $table) {
            $table->dropIndex('chat_events_created_at_idx');
            $table->dropIndex('chat_events_player_name_idx');
        });

        Schema::table('wargm_deliveries', function (Blueprint $table) {
            $table->dropIndex('wargm_deliveries_steam_card_idx');
            $table->dropColumn(['location', 'player_name', 'items_summary']);
        });
    }
};
