<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

            // Use prefix index on MySQL only (SQLite doesn't support prefix indexes)
            if (DB::connection()->getDriverName() === 'mysql' || DB::connection()->getDriverName() === 'mariadb') {
                $table->rawIndex('site_id, file_path(255), change_type', 'security_git_whitelists_unique');
                $table->rawIndex('site_id, file_path(255)', 'security_git_whitelists_site_id_file_path_index');
            } else {
                $table->index(['site_id', 'change_type'], 'security_git_whitelists_unique');
                $table->index(['site_id'], 'security_git_whitelists_site_id_file_path_index');
            }
        });
    }
};
