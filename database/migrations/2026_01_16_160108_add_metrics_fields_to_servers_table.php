<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // System metrics
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->decimal('load_avg_1', 5, 2)->nullable();
            $table->decimal('load_avg_5', 5, 2)->nullable();
            $table->decimal('load_avg_15', 5, 2)->nullable();

            // CPU metrics
            $table->unsignedTinyInteger('cpu_percent')->nullable();

            // RAM metrics
            $table->unsignedBigInteger('ram_total_bytes')->nullable();
            $table->unsignedBigInteger('ram_used_bytes')->nullable();
            $table->unsignedTinyInteger('ram_percent')->nullable();

            // Disk metrics
            $table->unsignedBigInteger('disk_total_bytes')->nullable();
            $table->unsignedBigInteger('disk_used_bytes')->nullable();
            $table->unsignedTinyInteger('disk_percent')->nullable();

            // Timestamp
            $table->timestamp('last_metrics_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'uptime_seconds',
                'load_avg_1',
                'load_avg_5',
                'load_avg_15',
                'cpu_percent',
                'ram_total_bytes',
                'ram_used_bytes',
                'ram_percent',
                'disk_total_bytes',
                'disk_used_bytes',
                'disk_percent',
                'last_metrics_at',
            ]);
        });
    }
};
