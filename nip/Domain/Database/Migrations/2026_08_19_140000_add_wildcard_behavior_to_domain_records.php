<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nip\Domain\Enums\WildcardBehavior;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_records', function (Blueprint $table) {
            $table->string('wildcard_behavior')
                ->default(WildcardBehavior::Serve->value)
                ->after('allow_wildcard');
        });
    }

    public function down(): void
    {
        Schema::table('domain_records', function (Blueprint $table) {
            $table->dropColumn('wildcard_behavior');
        });
    }
};
