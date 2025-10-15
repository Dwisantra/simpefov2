<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Unit;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'IT Support', 'instansi' => 'wiradadi'],
            ['name' => 'Operasional', 'instansi' => 'wiradadi'],
            ['name' => 'Keuangan', 'instansi' => 'raffa'],
            ['name' => 'Pelayanan Medis', 'instansi' => 'raffa'],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(
                ['name' => $unit['name'], 'instansi' => $unit['instansi']],
                ['is_active' => true]
            );
        }
    }
}
