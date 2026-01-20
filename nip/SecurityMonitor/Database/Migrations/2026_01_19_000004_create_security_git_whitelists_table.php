<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_git_whitelists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('file_path', 1000);
            $table->string('change_type')->default('any');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->rawIndex('site_id, file_path(255), change_type', 'security_git_whitelists_unique');
            $table->rawIndex('site_id, file_path(255)', 'security_git_whitelists_site_id_file_path_index');
        });
    }
};
