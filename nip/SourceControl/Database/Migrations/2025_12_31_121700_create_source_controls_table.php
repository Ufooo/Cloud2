<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // github, gitlab, bitbucket
            $table->string('name'); // Display name (e.g., GitHub username)
            $table->string('provider_user_id')->nullable(); // Provider's user ID
            $table->text('token'); // Encrypted access token
            $table->text('refresh_token')->nullable(); // Encrypted refresh token
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
        });
    }
};
