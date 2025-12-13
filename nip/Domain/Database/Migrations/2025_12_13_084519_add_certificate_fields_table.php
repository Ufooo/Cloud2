<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Let's Encrypt fields
            $table->string('verification_method')->nullable()->after('path');
            $table->json('verification_records')->nullable()->after('verification_method');
            $table->string('key_algorithm')->nullable()->after('verification_records');
            $table->boolean('isrg_root_chain')->default(false)->after('key_algorithm');

            // CSR fields
            $table->string('csr_country')->nullable()->after('isrg_root_chain');
            $table->string('csr_state')->nullable()->after('csr_country');
            $table->string('csr_city')->nullable()->after('csr_state');
            $table->string('csr_organization')->nullable()->after('csr_city');
            $table->string('csr_department')->nullable()->after('csr_organization');

            // Clone source
            $table->foreignId('source_certificate_id')->nullable()->after('csr_department')
                ->constrained('certificates')->nullOnDelete();
        });
    }
};
