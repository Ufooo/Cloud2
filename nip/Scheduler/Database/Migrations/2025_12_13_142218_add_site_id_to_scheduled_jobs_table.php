<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_jobs', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('server_id')
                ->constrained('sites')->cascadeOnDelete();

            $table->index(['site_id', 'status']);
        });
    }
};
