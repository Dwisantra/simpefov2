<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feature_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('development_status')
                ->default(1)
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('feature_requests', function (Blueprint $table) {
            $table->dropColumn('development_status');
        });
    }
};
