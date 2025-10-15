<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $map = [
            'user' => UserRole::USER->value,
            'manager' => UserRole::MANAGER->value,
            'director_a' => UserRole::DIRECTOR_A->value,
            'director_b' => UserRole::DIRECTOR_B->value,
            'admin' => UserRole::ADMIN->value,
        ];

        foreach ($map as $string => $id) {
            DB::table('users')->where('level', $string)->update(['level' => $id]);
            DB::table('approvals')->where('role', $string)->update(['role' => $id]);
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `users` MODIFY `level` TINYINT UNSIGNED NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE `approvals` MODIFY `role` TINYINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN level TYPE SMALLINT USING level::integer');
            DB::statement('ALTER TABLE users ALTER COLUMN level SET NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN level SET DEFAULT 1');
            DB::statement('ALTER TABLE approvals ALTER COLUMN role TYPE SMALLINT USING role::integer');
            DB::statement('ALTER TABLE approvals ALTER COLUMN role SET NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `approvals` MODIFY `role` VARCHAR(32) NOT NULL");
            DB::statement("ALTER TABLE `users` MODIFY `level` VARCHAR(32) NOT NULL DEFAULT 'user'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE approvals ALTER COLUMN role TYPE VARCHAR(32)');
            DB::statement('ALTER TABLE approvals ALTER COLUMN role SET NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN level TYPE VARCHAR(32)');
            DB::statement('ALTER TABLE users ALTER COLUMN level SET NOT NULL');
            DB::statement("ALTER TABLE users ALTER COLUMN level SET DEFAULT 'user'");
        }

        $reverse = [
            UserRole::USER->value => 'user',
            UserRole::MANAGER->value => 'manager',
            UserRole::DIRECTOR_A->value => 'director_a',
            UserRole::DIRECTOR_B->value => 'director_b',
            UserRole::ADMIN->value => 'admin',
        ];

        foreach ($reverse as $id => $string) {
            DB::table('users')->where('level', $id)->update(['level' => $string]);
            DB::table('approvals')->where('role', $id)->update(['role' => $string]);
        }
    }
};
