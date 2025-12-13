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
        Schema::table('background_processes', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('server_id')
                ->constrained('sites')->cascadeOnDelete();
            $table->index(['site_id', 'status']);
        });
    }
};
