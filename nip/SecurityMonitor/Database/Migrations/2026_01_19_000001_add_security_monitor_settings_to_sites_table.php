<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->unsignedInteger('security_scan_interval_minutes')->default(60);
            $table->unsignedInteger('security_scan_retention_days')->default(7);
            $table->boolean('git_monitor_enabled')->default(true);
        });
    }
};
