<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('security_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('name');
            $table->string('path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });

        Schema::create('security_rule_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_rule_id')->constrained('security_rules')->cascadeOnDelete();
            $table->string('username');
            $table->string('password');
            $table->timestamps();
        });
    }
};
