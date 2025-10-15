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
            'user'       => \App\Enums\UserRole::USER->value,
            'manager'    => \App\Enums\UserRole::MANAGER->value,
            'director_a' => \App\Enums\UserRole::DIRECTOR_A->value,
            'director_b' => \App\Enums\UserRole::DIRECTOR_B->value,
            'admin'      => \App\Enums\UserRole::ADMIN->value,
        ];

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
            UPDATE `users`
            SET `level` = CASE CAST(`level` AS CHAR)
                WHEN 'user'       THEN {$map['user']}
                WHEN 'manager'    THEN {$map['manager']}
                WHEN 'director_a' THEN {$map['director_a']}
                WHEN 'director_b' THEN {$map['director_b']}
                WHEN 'admin'      THEN {$map['admin']}
                ELSE `level`
            END
        ");
            DB::statement("
            UPDATE `approvals`
            SET `role` = CASE CAST(`role` AS CHAR)
                WHEN 'user'       THEN {$map['user']}
                WHEN 'manager'    THEN {$map['manager']}
                WHEN 'director_a' THEN {$map['director_a']}
                WHEN 'director_b' THEN {$map['director_b']}
                WHEN 'admin'      THEN {$map['admin']}
                ELSE `role`
            END
        ");
            DB::statement('ALTER TABLE `users` MODIFY `level` TINYINT UNSIGNED NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE `approvals` MODIFY `role` TINYINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement("
            UPDATE users
            SET level = CASE (level)::text
                WHEN 'user'       THEN {$map['user']}
                WHEN 'manager'    THEN {$map['manager']}
                WHEN 'director_a' THEN {$map['director_a']}
                WHEN 'director_b' THEN {$map['director_b']}
                WHEN 'admin'      THEN {$map['admin']}
                ELSE level
            END
        ");
            DB::statement("
            UPDATE approvals
            SET role = CASE (role)::text
                WHEN 'user'       THEN {$map['user']}
                WHEN 'manager'    THEN {$map['manager']}
                WHEN 'director_a' THEN {$map['director_a']}
                WHEN 'director_b' THEN {$map['director_b']}
                WHEN 'admin'      THEN {$map['admin']}
                ELSE role
            END
        ");
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
            \App\Enums\UserRole::USER->value       => 'user',
            \App\Enums\UserRole::MANAGER->value    => 'manager',
            \App\Enums\UserRole::DIRECTOR_A->value => 'director_a',
            \App\Enums\UserRole::DIRECTOR_B->value => 'director_b',
            \App\Enums\UserRole::ADMIN->value      => 'admin',
        ];

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
            UPDATE `users`
            SET `level` = CASE CAST(`level` AS CHAR)
                WHEN '{$reverse[\App\Enums\UserRole::USER->value]}'       THEN 'user'
                WHEN '{$reverse[\App\Enums\UserRole::MANAGER->value]}'    THEN 'manager'
                WHEN '{$reverse[\App\Enums\UserRole::DIRECTOR_A->value]}' THEN 'director_a'
                WHEN '{$reverse[\App\Enums\UserRole::DIRECTOR_B->value]}' THEN 'director_b'
                WHEN '{$reverse[\App\Enums\UserRole::ADMIN->value]}'      THEN 'admin'
                ELSE `level`
            END
        ");

            DB::statement("
            UPDATE `approvals`
            SET `role` = CASE CAST(`role` AS CHAR)
                WHEN '{$reverse[\App\Enums\UserRole::USER->value]}'       THEN 'user'
                WHEN '{$reverse[\App\Enums\UserRole::MANAGER->value]}'    THEN 'manager'
                WHEN '{$reverse[\App\Enums\UserRole::DIRECTOR_A->value]}' THEN 'director_a'
                WHEN '{$reverse[\App\Enums\UserRole::DIRECTOR_B->value]}' THEN 'director_b'
                WHEN '{$reverse[\App\Enums\UserRole::ADMIN->value]}'      THEN 'admin'
                ELSE `role`
            END
        ");
        } else {
            DB::statement("
            UPDATE users
            SET level = CASE (level)::text
                WHEN '{$reverse[\App\Enums\UserRole::USER->value]}'       THEN 'user'
                WHEN '{$reverse[\App\Enums\UserRole::MANAGER->value]}'    THEN 'manager'
                WHEN '{$reverse[\App\Enums\UserRole::DIRECTOR_A->value]}' THEN 'director_a'
                WHEN '{$reverse[\App\Enums\UserRole::DIRECTOR_B->value]}' THEN 'director_b'
                WHEN '{$reverse[\App\Enums\UserRole::ADMIN->value]}'      THEN 'admin'
                ELSE level
            END
        ");

            DB::statement("
            UPDATE approvals
            SET role = CASE (role)::text
                WHEN '{$reverse[\App\Enums\UserRole::USER->value]}'       THEN 'user'
                WHEN '{$reverse[\App\Enums\UserRole::MANAGER->value]}'    THEN 'manager'
                WHEN '{$reverse[\App\Enums\UserRole::DIRECTOR_A->value]}' THEN 'director_a'
                WHEN '{$reverse[\App\Enums\UserRole::DIRECTOR_B->value]}' THEN 'director_b'
                WHEN '{$reverse[\App\Enums\UserRole::ADMIN->value]}'      THEN 'admin'
                ELSE role
            END
        ");
        }
    }
};
