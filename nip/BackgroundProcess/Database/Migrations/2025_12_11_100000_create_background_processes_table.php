<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('background_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('command');
            $table->string('directory')->nullable();
            $table->string('user')->default('netipar');
            $table->unsignedInteger('processes')->default(1);
            $table->unsignedInteger('startsecs')->default(1);
            $table->unsignedInteger('stopwaitsecs')->default(15);
            $table->string('stopsignal')->default('TERM');
            $table->string('status')->default('pending');
            $table->string('supervisor_process_status')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('background_processes');
    }
};
