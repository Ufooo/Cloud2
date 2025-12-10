<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unix_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('username');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['server_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unix_users');
    }
};
