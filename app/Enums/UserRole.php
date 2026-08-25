<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'Superadmin';
    case Admin = 'Admin';
    case ManagerTeknisi = 'Manager Teknisi';
    case Noc = 'NOC';
    case Teknisi = 'Teknisi';
    case CustomerService = 'CS';
    case Marketing = 'Marketing';

    /**
     * Get all role names as strings.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the human readable description of the role.
     */
    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Administrator'),
            self::Admin => __('Administrator'),
            self::ManagerTeknisi => __('Manager Technic Network Infrastruktur Sistem Internet'),
            self::Noc => __('Network Office Center'),
            self::Teknisi => __('Technic Network Infrastruktur Sistem Internet'),
            self::CustomerService => __('Customer Service'),
            self::Marketing => __('Marketing'),
        };
    }
}
