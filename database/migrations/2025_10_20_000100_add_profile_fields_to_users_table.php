<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->enum('instansi', ['wiradadi', 'raffa'])->nullable()->after('phone');
            $table->foreignId('unit_id')->nullable()->after('instansi')->constrained('units')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('kode_sign');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn(['phone', 'instansi', 'verified_at']);
        });
    }
};
