<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssh_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('unix_user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('name');
            $table->text('public_key');
            $table->string('fingerprint');
            $table->timestamps();

            $table->unique(['server_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_keys');
    }
};
