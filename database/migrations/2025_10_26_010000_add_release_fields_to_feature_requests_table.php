<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feature_requests', function (Blueprint $table) {
            $table->date('release_date')->nullable()->after('development_status');
            $table->unsignedTinyInteger('release_status')->nullable()->after('release_date');
            $table->foreignId('release_set_by')->nullable()->after('release_status')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feature_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('release_set_by');
            $table->dropColumn(['release_date', 'release_status']);
        });
    }
};
