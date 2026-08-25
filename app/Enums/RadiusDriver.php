<?php

namespace App\Enums;

enum RadiusDriver: string
{
    case MySQL = 'mysql';
    case PostgreSQL = 'pgsql';

    /**
     * Get all driver values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the assignable driver options with their labels.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::MySQL->value, 'label' => 'MySQL'],
            ['value' => self::PostgreSQL->value, 'label' => 'PostgreSQL'],
        ];
    }

    /**
     * Get the default port of the driver.
     */
    public function defaultPort(): int
    {
        return match ($this) {
            self::MySQL => 3306,
            self::PostgreSQL => 5432,
        };
    }

    /**
     * Get the display label of the driver.
     */
    public function label(): string
    {
        return match ($this) {
            self::MySQL => 'MySQL',
            self::PostgreSQL => 'PostgreSQL',
        };
    }
}
