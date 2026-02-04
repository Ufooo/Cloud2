<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openvpn_clients', function (Blueprint $table) {
            $table->id();
            $table->string('common_name')->unique();
            $table->string('real_address')->nullable();
            $table->string('virtual_address')->nullable();
            $table->unsignedBigInteger('bytes_received')->default(0);
            $table->unsignedBigInteger('bytes_sent')->default(0);
            $table->timestamp('connected_since')->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }
};
