<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('letsencrypt');
            $table->string('status')->default('pending');
            $table->json('domains');
            $table->boolean('active')->default(false);
            $table->text('certificate')->nullable();
            $table->text('private_key')->nullable();
            $table->string('path')->nullable();
            // Let's Encrypt fields
            $table->string('verification_method')->nullable();
            $table->json('verification_records')->nullable();
            $table->json('acme_subdomains')->nullable();
            $table->string('key_algorithm')->nullable();
            $table->boolean('isrg_root_chain')->default(false);
            // CSR fields
            $table->string('csr_country')->nullable();
            $table->string('csr_state')->nullable();
            $table->string('csr_city')->nullable();
            $table->string('csr_organization')->nullable();
            $table->string('csr_department')->nullable();
            // Clone source
            $table->foreignId('source_certificate_id')->nullable()->constrained('certificates')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'active']);
            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
