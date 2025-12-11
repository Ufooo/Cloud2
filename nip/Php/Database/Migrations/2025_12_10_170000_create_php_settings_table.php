<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('php_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->integer('max_upload_size')->nullable();
            $table->integer('max_execution_time')->nullable();
            $table->boolean('opcache_enabled')->default(false);
            $table->timestamps();

            $table->unique('server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('php_settings');
    }
};
