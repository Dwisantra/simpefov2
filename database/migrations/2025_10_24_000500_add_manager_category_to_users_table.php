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
        // 1) Tambah kolom ID (numerik) terlebih dahulu
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'manager_category_id')) {
                $table->unsignedTinyInteger('manager_category_id')->nullable()->after('level');
            }
        });

        $map = [
            'yanmum'  => ManagerCategory::YANMUM->value,
            'yanmed'  => ManagerCategory::YANMED->value,
            'jangmed' => ManagerCategory::JANGMED->value,
        ];

        $driver = DB::getDriverName();

        // Deteksi apakah ada kolom sumber teks 'manager_category'
        $hasSourceTextCol = Schema::hasColumn('users', 'manager_category');

        // Siapkan ekspresi CAST sesuai driver
        $cast = fn(string $col) => $driver === 'mysql'
            ? "CAST(`{$col}` AS CHAR)"
            : ($driver === 'pgsql' ? "CAST({$col} AS TEXT)" : $col);

        DB::beginTransaction();
        try {
            if ($hasSourceTextCol) {
                // 2A) Mapping dari kolom teks 'manager_category' -> kolom ID
                $sourceCol = 'manager_category';
                $castSource = $cast($sourceCol);

                foreach ($map as $label => $id) {
                    DB::table('users')
                        ->whereRaw("$castSource = ?", [$label])
                        ->update(['manager_category_id' => $id]);
                }
            } else {
                // 2B) Fallback: jika (dalam skema lama) label pernah tersimpan di kolom yang sama,
                // gunakan CAST kolom ID sebagai teks untuk memetakan, lalu set ke angka.
                // Aman walau sekarang kolom sudah numerik & NULL: kondisi tidak akan match dan tidak error.
                $castId = $cast('manager_category_id');

                foreach ($map as $label => $id) {
                    DB::table('users')
                        ->whereRaw("$castId = ?", [$label])
                        ->update(['manager_category_id' => $id]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
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
