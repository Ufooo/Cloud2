<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('slug')->unique();
            $table->string('type')->default('other');
            $table->string('status')->default('pending');
            $table->string('deploy_status')->default('never_deployed');
            $table->string('www_redirect_type')->default('from_www');
            $table->boolean('allow_wildcard')->default(false);
            $table->string('user')->default('netipar');
            $table->string('root_directory')->default('/');
            $table->string('web_directory')->default('/public');
            $table->string('php_version')->nullable();
            $table->string('package_manager')->nullable();
            $table->string('build_command')->nullable();
            $table->string('repository')->nullable();
            $table->string('branch')->nullable();
            $table->text('deploy_key')->nullable();
            $table->text('deploy_script')->nullable();
            $table->text('environment')->nullable();
            $table->string('avatar_color')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'domain']);
            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
