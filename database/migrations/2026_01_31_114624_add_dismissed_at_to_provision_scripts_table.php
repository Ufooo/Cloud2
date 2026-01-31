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
        Schema::table('provision_scripts', function (Blueprint $table) {
            $table->timestamp('dismissed_at')->nullable()->after('status');
        });
    }
};
