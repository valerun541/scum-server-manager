<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (! Schema::hasColumn('servers', 'ftp_config_path')) {
                $table->string('ftp_config_path')->default('SCUM/Saved/Config/WindowsServer')->after('ftp_scumdb_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'ftp_config_path')) {
                $table->dropColumn('ftp_config_path');
            }
        });
    }
};
