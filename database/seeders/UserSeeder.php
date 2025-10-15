<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(UnitSeeder::class);

        $wiradadiIt = Unit::firstWhere([
            'name' => 'IT Support',
            'instansi' => 'wiradadi',
        ]);

        $wiradadiOps = Unit::firstWhere([
            'name' => 'Operasional',
            'instansi' => 'wiradadi',
        ]);

        $raffaFinance = Unit::firstWhere([
            'name' => 'Keuangan',
            'instansi' => 'raffa',
        ]);

        $raffaMedical = Unit::firstWhere([
            'name' => 'Pelayanan Medis',
            'instansi' => 'raffa',
        ]);

        $now = now();

        // Admin (akses penuh)
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Sistem',
                'password' => Hash::make('password'),
                'level' => UserRole::ADMIN->value,
                'kode_sign' => Hash::make('ADM001'),
                'phone' => '081200000001',
                'instansi' => 'wiradadi',
                'unit_id' => $wiradadiIt?->id,
                'verified_at' => $now,
            ]
        );

        // Manager (review & acc sebelum direktur)
        User::updateOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager Fitur',
                'password' => Hash::make('password'),
                'level' => UserRole::MANAGER->value,
                'kode_sign' => Hash::make('MGR001'),
                'phone' => '081200000002',
                'instansi' => 'wiradadi',
                'unit_id' => $wiradadiOps?->id,
                'verified_at' => $now,
            ]
        );

        // Direktur RS A
        User::updateOrCreate(
            ['email' => 'direktura@example.com'],
            [
                'name' => 'Direktur RS A',
                'password' => Hash::make('password'),
                'level' => UserRole::DIRECTOR_A->value,
                'kode_sign' => Hash::make('DIRA01'),
                'phone' => '081200000003',
                'instansi' => 'wiradadi',
                'unit_id' => $wiradadiIt?->id,
                'verified_at' => $now,
            ]
        );

        // Direktur RS B
        User::updateOrCreate(
            ['email' => 'direkturb@example.com'],
            [
                'name' => 'Direktur RS B',
                'password' => Hash::make('password'),
                'level' => UserRole::DIRECTOR_B->value,
                'kode_sign' => Hash::make('DIRB01'),
                'phone' => '081200000004',
                'instansi' => 'raffa',
                'unit_id' => $raffaMedical?->id,
                'verified_at' => $now,
            ]
        );

        // User (pengaju request)
        User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'User Pemohon',
                'password' => Hash::make('password'),
                'level' => UserRole::USER->value,
                'kode_sign' => Hash::make('USR001'),
                'phone' => '081200000005',
                'instansi' => 'raffa',
                'unit_id' => $raffaFinance?->id,
                'verified_at' => $now,
            ]
        );
    }
}
