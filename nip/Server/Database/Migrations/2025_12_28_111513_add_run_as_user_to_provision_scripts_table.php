<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provision_scripts', function (Blueprint $table) {
            $table->string('run_as_user')->nullable()->after('resource_id');
        });
    }
};
