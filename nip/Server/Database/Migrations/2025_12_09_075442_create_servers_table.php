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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('provider');
            $table->string('provider_server_id')->nullable();
            $table->string('type')->default('app');
            $table->string('status')->default('provisioning');
            $table->string('provisioning_token', 64)->nullable();
            $table->unsignedTinyInteger('provision_step')->default(0);
            $table->string('ip_address')->nullable();
            $table->string('private_ip_address')->nullable();
            $table->string('ssh_port')->default('22');
            $table->string('php_version')->default('php83');
            $table->string('database_type')->nullable();
            $table->string('db_status')->nullable();
            $table->string('ubuntu_version')->nullable();
            $table->string('timezone')->default('UTC');
            $table->text('notes')->nullable();
            $table->string('avatar_color')->default('blue');
            $table->json('services')->nullable();
            $table->json('region')->nullable();
            $table->string('displayable_provider')->nullable();
            $table->string('displayable_database_type')->nullable();
            $table->string('cloud_provider_url')->nullable();
            $table->boolean('is_ready')->default(false);
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
