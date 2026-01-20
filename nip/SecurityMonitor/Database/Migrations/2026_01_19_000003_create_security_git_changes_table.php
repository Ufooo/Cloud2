<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_git_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('security_scans')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('file_path', 1000);
            $table->string('change_type');
            $table->string('git_status_code', 2);
            $table->boolean('is_whitelisted')->default(false);
            $table->timestamp('whitelisted_at')->nullable();
            $table->foreignId('whitelisted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('whitelist_reason', 500)->nullable();
            $table->timestamps();

            $table->rawIndex('site_id, file_path(255)', 'security_git_changes_site_id_file_path_index');
            $table->index(['site_id', 'is_whitelisted']);
            $table->index(['scan_id', 'is_whitelisted']);
        });
    }
};
