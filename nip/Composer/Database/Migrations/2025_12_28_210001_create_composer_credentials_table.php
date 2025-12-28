<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('composer_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unix_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('repository');
            $table->string('username');
            $table->text('password');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('unix_user_id');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('composer_credentials');
    }
};
