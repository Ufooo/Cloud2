<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('username');
            $table->boolean('readonly')->default(false);
            $table->timestamps();

            $table->unique(['server_id', 'username']);
        });

        Schema::create('database_database_user', function (Blueprint $table) {
            $table->foreignId('database_id')->constrained()->cascadeOnDelete();
            $table->foreignId('database_user_id')->constrained()->cascadeOnDelete();

            $table->primary(['database_id', 'database_user_id']);
        });
    }
};
