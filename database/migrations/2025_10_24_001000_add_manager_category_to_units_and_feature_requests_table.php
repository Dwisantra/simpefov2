<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedTinyInteger('manager_category_id')->nullable()->after('instansi');
        });

        Schema::table('feature_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('manager_category_id')->nullable()->after('requester_instansi');
        });

        // Ensure existing feature requests inherit the manager category from the requester's unit if available.
        $featureRequests = DB::table('feature_requests as fr')
            ->join('users as u', 'fr.user_id', '=', 'u.id')
            ->leftJoin('units as un', 'u.unit_id', '=', 'un.id')
            ->select('fr.id', 'un.manager_category_id')
            ->get();

        foreach ($featureRequests as $row) {
            if ($row->manager_category_id) {
                DB::table('feature_requests')
                    ->where('id', $row->id)
                    ->update(['manager_category_id' => $row->manager_category_id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feature_requests', function (Blueprint $table) {
            $table->dropColumn('manager_category_id');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('manager_category_id');
        });
    }
};
