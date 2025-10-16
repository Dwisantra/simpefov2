<?php

namespace Database\Seeders;

use App\Enums\ManagerCategory;
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
            ['username' => 'admin'],
            [
                'username' => 'admin',
                'name' => 'Admin Sistem',
                'email' => 'admin@example.com',
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
            ['username' => 'manager.yanmum'],
            [
                'username' => 'manager.yanmum',
                'name' => 'Manager yanmum',
                'email' => 'manager.yanmum@example.com',
                'password' => Hash::make('password'),
                'level' => UserRole::MANAGER->value,
                'manager_category_id' => ManagerCategory::YANMUM->value,
                'kode_sign' => Hash::make('MGR001'),
                'phone' => '081200000002',
                'instansi' => 'wiradadi',
                'unit_id' => $wiradadiOps?->id,
                'verified_at' => $now,
            ]
        );

        User::updateOrCreate(
            ['username' => 'manager.yanmed'],
            [
                'username' => 'manager.yanmed',
                'name' => 'Manager Yanmed',
                'email' => 'manager.yanmed@example.com',
                'password' => Hash::make('password'),
                'level' => UserRole::MANAGER->value,
                'manager_category_id' => ManagerCategory::YANMED->value,
                'kode_sign' => Hash::make('MGR002'),
                'phone' => '081200000006',
                'instansi' => 'wiradadi',
                'unit_id' => $wiradadiOps?->id,
                'verified_at' => $now,
            ]
        );

        User::updateOrCreate(
            ['username' => 'manager.jangmed'],
            [
                'username' => 'manager.jangmed',
                'name' => 'Manager Jangmed',
                'email' => 'manager.jangmed@example.com',
                'password' => Hash::make('password'),
                'level' => UserRole::MANAGER->value,
                'manager_category_id' => ManagerCategory::JANGMED->value,
                'kode_sign' => Hash::make('MGR003'),
                'phone' => '081200000007',
                'instansi' => 'wiradadi',
                'unit_id' => $wiradadiOps?->id,
                'verified_at' => $now,
            ]
        );

        // Direktur RS A
        User::updateOrCreate(
            ['username' => 'direktura'],
            [
                'username' => 'direktura',
                'name' => 'Direktur RS A',
                'email' => 'direktura@example.com',
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
            ['username' => 'direkturb'],
            [
                'username' => 'direkturb',
                'name' => 'Direktur RS B',
                'email' => 'direkturb@example.com',
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
            ['username' => 'user.pemohon'],
            [
                'username' => 'user.pemohon',
                'name' => 'User Pemohon',
                'email' => 'user@example.com',
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
