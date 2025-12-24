<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provision_scripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->text('content');
            $table->longText('output')->nullable();
            $table->integer('exit_code')->nullable();
            $table->enum('status', ['pending', 'executing', 'completed', 'failed'])->default('pending');
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'status']);
            $table->index(['resource_type', 'resource_id']);
            $table->index('created_at');
        });
    }
};
