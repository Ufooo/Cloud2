<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirect_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('from');
            $table->string('to');
            $table->string('type')->default('permanent');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }
};
