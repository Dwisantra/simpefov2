<?php

namespace Database\Seeders;

use App\Enums\ManagerCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            [
                'name' => 'IT Support',
                'instansi' => 'wiradadi',
                'manager_category_id' => ManagerCategory::JANGMED->value,
            ],
            [
                'name' => 'Operasional',
                'instansi' => 'wiradadi',
                'manager_category_id' => ManagerCategory::YANMUM->value,
            ],
            [
                'name' => 'Keuangan',
                'instansi' => 'raffa',
                'manager_category_id' => ManagerCategory::YANMUM->value,
            ],
            [
                'name' => 'Pelayanan Medis',
                'instansi' => 'raffa',
                'manager_category_id' => ManagerCategory::YANMED->value,
            ],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(
                ['name' => $unit['name'], 'instansi' => $unit['instansi']],
                [
                    'is_active' => true,
                    'manager_category_id' => $unit['manager_category_id'],
                ]
            );
        }
    }
}
