<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('php_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->boolean('is_cli_default')->default(false);
            $table->boolean('is_site_default')->default(false);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['server_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('php_versions');
    }
};
