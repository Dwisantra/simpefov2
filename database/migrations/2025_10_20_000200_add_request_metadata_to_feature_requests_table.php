<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feature_requests', function (Blueprint $table) {
            $table->json('request_types')->nullable()->after('user_id');
            $table->string('requester_unit')->nullable()->after('status');
            $table->string('requester_instansi')->nullable()->after('requester_unit');
        });
    }

    public function down(): void
    {
        Schema::table('feature_requests', function (Blueprint $table) {
            $table->dropColumn(['request_types', 'requester_unit', 'requester_instansi']);
        });
    }
};
