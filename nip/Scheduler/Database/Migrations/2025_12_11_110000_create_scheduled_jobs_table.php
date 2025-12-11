<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('command');
            $table->string('user')->default('netipar');
            $table->string('frequency')->default('weekly');
            $table->string('cron')->nullable();
            $table->boolean('heartbeat_enabled')->default(false);
            $table->string('heartbeat_url')->nullable();
            $table->unsignedInteger('grace_period')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_jobs');
    }
};
