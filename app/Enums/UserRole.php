<?php

namespace App\Enums;

enum UserRole: int
{
    case USER = 1;
    case MANAGER = 2;
    case DIRECTOR_A = 3;
    case DIRECTOR_B = 4;
    case ADMIN = 5;

    public function label(): string
    {
        return match ($this) {
            self::USER => 'Pemohon',
            self::MANAGER => 'Manager',
            self::DIRECTOR_A => 'Direktur RS Raffa Majenang',
            self::DIRECTOR_B => 'Direktur RS Wiradadi Husada',
            self::ADMIN => 'Administrator',
        };
    }

    public static function tryFromMixed(int|string|null $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || (is_numeric($value) && (int) $value == $value)) {
            return self::tryFrom((int) $value);
        }

        $normalized = strtolower((string) $value);

        return match ($normalized) {
            'user', 'pemohon' => self::USER,
            'manager' => self::MANAGER,
            'director_a', 'direktura', 'direktur_a' => self::DIRECTOR_A,
            'director_b', 'direkturb', 'direktur_b' => self::DIRECTOR_B,
            'admin', 'administrator' => self::ADMIN,
            default => null,
        };
    }
}
