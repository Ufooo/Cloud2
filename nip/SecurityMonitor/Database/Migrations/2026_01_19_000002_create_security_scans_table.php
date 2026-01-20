<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('git_modified_count')->default(0);
            $table->unsignedInteger('git_untracked_count')->default(0);
            $table->unsignedInteger('git_deleted_count')->default(0);
            $table->unsignedInteger('git_whitelisted_count')->default(0);
            $table->unsignedInteger('git_new_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'created_at']);
            $table->index(['server_id', 'created_at']);
        });
    }
};
