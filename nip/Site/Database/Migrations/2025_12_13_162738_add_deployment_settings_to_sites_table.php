<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('push_to_deploy')->default(false)->after('deploy_script');
            $table->boolean('auto_source')->default(false)->after('push_to_deploy');
            $table->string('deploy_hook_token')->nullable()->after('auto_source');
            $table->unsignedInteger('deployment_retention')->default(5)->after('deploy_hook_token');
            $table->boolean('zero_downtime')->default(false)->after('deployment_retention');
            $table->string('healthcheck_endpoint')->nullable()->after('zero_downtime');
        });
    }
};
