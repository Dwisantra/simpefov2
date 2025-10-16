<?php

namespace App\Enums;

enum ManagerCategory: int
{
    case YANMUM = 1;
    case YANMED = 2;
    case JANGMED = 3;

    public function label(): string
    {
        return match ($this) {
            self::YANMUM => 'Manager Yanmum',
            self::YANMED => 'Manager Yanmed',
            self::JANGMED => 'Manager Jangmed',
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
            'yanmum' => self::YANMUM,
            'yanmed' => self::YANMED,
            'jangmed' => self::JANGMED,
            default => null,
        };
    }
}
