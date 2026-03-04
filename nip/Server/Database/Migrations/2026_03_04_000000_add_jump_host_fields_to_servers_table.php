<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('ssh_user')->nullable()->after('ssh_port');
            $table->string('jump_address')->nullable()->after('ssh_user');
            $table->unsignedSmallInteger('jump_port')->nullable()->after('jump_address');
            $table->string('jump_user')->nullable()->after('jump_port');
        });
    }
};
