<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_records', function (Blueprint $table) {
            $table->string('redirect_target')->nullable()->after('name');
        });
    }
};
