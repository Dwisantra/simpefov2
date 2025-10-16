<?php

use App\Enums\ManagerCategory;
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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('manager_category_id')->nullable()->after('level');
        });

        $map = [
            'yanmum' => ManagerCategory::YANMUM->value,
            'yanmed' => ManagerCategory::YANMED->value,
            'jangmed' => ManagerCategory::JANGMED->value,
        ];

        foreach ($map as $string => $id) {
            DB::table('users')
                ->where('manager_category_id', $string)
                ->update(['manager_category_id' => $id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (ManagerCategory::cases() as $category) {
            DB::table('users')
                ->where('manager_category_id', $category->value)
                ->update(['manager_category_id' => null]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('manager_category_id');
        });
    }
};
