<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('env_clear_config_cache')->default(false)->after('zero_downtime');
            $table->boolean('env_restart_queue')->default(false)->after('env_clear_config_cache');
        });
    }
};
