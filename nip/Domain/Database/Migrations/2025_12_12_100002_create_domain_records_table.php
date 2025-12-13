<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('alias');
            $table->string('status')->default('pending');
            $table->string('www_redirect_type')->default('from_www');
            $table->boolean('allow_wildcard')->default(false);
            $table->timestamps();

            $table->unique(['site_id', 'name']);
            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_records');
    }
};
