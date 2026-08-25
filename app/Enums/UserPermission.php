<?php

namespace App\Enums;

enum UserPermission: string
{
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';
    case GatewaysView = 'gateways.view';
    case GatewaysUpdate = 'gateways.update';
    case ServersView = 'servers.view';
    case ServersUpdate = 'servers.update';
    case FtthView = 'ftth.view';
    case FtthUpdate = 'ftth.update';
    case MapsResolve = 'maps.resolve';
    case PppView = 'ppp.view';
    case PppUpdate = 'ppp.update';
    case ProspectsView = 'prospects.view';
    case ProspectsCreate = 'prospects.create';
    case ProspectsApprove = 'prospects.approve';
    case ProspectsDelete = 'prospects.delete';
    case TicketsView = 'tickets.view';
    case TicketsUpdate = 'tickets.update';
    case PelangganView = 'pelanggan.view';
    case PelangganUpdate = 'pelanggan.update';
    case PelangganDelete = 'pelanggan.delete';
    case WaView = 'wa.view';
    case WaSend = 'wa.send';
    case WaManage = 'wa.manage';
    case BillingView = 'billing.view';
    case BillingUpdate = 'billing.update';
    case BillingDelete = 'billing.delete';
    case PackagesView = 'packages.view';
    case PackagesCreate = 'packages.create';
    case PackagesUpdate = 'packages.update';
    case PackagesDelete = 'packages.delete';

    /**
     * Get all permission names as strings.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the permissions assigned to the given role.
     *
     * @return array<int, string>
     */
    public static function forRole(UserRole $role): array
    {
        return match ($role) {
            UserRole::SuperAdmin => self::values(),
            UserRole::Admin => [
                self::UsersView->value,
                self::UsersCreate->value,
                self::UsersUpdate->value,
                self::ProspectsView->value,
                self::ProspectsApprove->value,
                self::TicketsView->value,
                self::TicketsUpdate->value,
                self::PelangganView->value,
                self::PelangganUpdate->value,
                self::PelangganDelete->value,
                self::WaView->value,
                self::WaSend->value,
                self::WaManage->value,
                self::BillingView->value,
                self::BillingUpdate->value,
            ],
            UserRole::Marketing => [
                self::ProspectsView->value,
                self::ProspectsCreate->value,
                self::MapsResolve->value,
                self::TicketsView->value,
                self::PelangganView->value,
                self::WaView->value,
                self::WaSend->value,
            ],
            UserRole::Teknisi => [
                self::ProspectsView->value,
                self::ProspectsCreate->value,
                self::MapsResolve->value,
                self::TicketsView->value,
                self::TicketsUpdate->value,
                self::PelangganView->value,
                self::PelangganUpdate->value,
                self::WaView->value,
                self::WaSend->value,
            ],
            default => [],
        };
    }
}
