<?php

namespace App\Enums;

enum MenuPermission: string
{
    case Pelanggans = 'menus.pelanggans';
    case Prospects = 'menus.prospects';
    case Packages = 'menus.packages';
    case Pppoe = 'menus.pppoe';
    case PppProfiles = 'menus.ppp-profiles';
    case PppSecrets = 'menus.ppp-secrets';
    case PppActive = 'menus.ppp-active';
    case Ftth = 'menus.ftth';
    case AreaCodes = 'menus.area-codes';
    case OdpOdc = 'menus.odp-odc';
    case Onu = 'menus.onu';
    case Tickets = 'menus.tickets';
    case PsbTickets = 'menus.tickets-psb';
    case Billing = 'menus.billing';
    case BillingSetting = 'menus.billing-setting';
    case BillingInvoices = 'menus.billing-invoices';
    case BillingCustomers = 'menus.billing-customers';
    case Whatsapp = 'menus.whatsapp';
    case WaTemplates = 'menus.wa-templates';
    case WaBroadcast = 'menus.wa-broadcast';
    case BillingBills = 'menus.billing-bills';
    case BillingReports = 'menus.billing-reports';
    case Servers = 'menus.servers';
    case Mikrotik = 'menus.mikrotik';
    case Radius = 'menus.radius';
    case Genieacs = 'menus.genieacs';
    case Gateways = 'menus.gateways';
    case Midtrans = 'menus.midtrans';
    case Waha = 'menus.waha';
    case Users = 'menus.users';
    case Roles = 'menus.roles';
    case Permissions = 'menus.permissions';

    /**
     * Get all menu permission names as strings.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the assignable menu options with their labels.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $menu): array => ['value' => $menu->value, 'label' => $menu->label()],
            self::cases(),
        );
    }

    /**
     * Determine whether the given permission name is a menu permission.
     */
    public static function isMenuPermission(string $name): bool
    {
        return in_array($name, self::values(), true);
    }

    /**
     * Get the default menus assigned to the given role.
     *
     * @return array<int, string>
     */
    public static function forRole(UserRole $role): array
    {
        return match ($role) {
            UserRole::SuperAdmin => self::values(),
            UserRole::Admin => [self::Users->value, self::Pelanggans->value, self::Prospects->value, self::Tickets->value, self::Billing->value, self::BillingSetting->value, self::BillingBills->value, self::BillingReports->value, self::BillingCustomers->value, self::Whatsapp->value, self::WaTemplates->value, self::WaBroadcast->value],
            UserRole::Marketing => [self::Pelanggans->value, self::Prospects->value, self::Tickets->value, self::Whatsapp->value, self::WaBroadcast->value],
            UserRole::Teknisi => [self::Pelanggans->value, self::Prospects->value, self::Tickets->value, self::Whatsapp->value, self::WaBroadcast->value],
            default => [],
        };
    }

    /**
     * Get the display label of the menu.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pelanggans => __('Pelanggan'),
            self::Prospects => __('Prospect'),
            self::Packages => __('Paket & Harga'),
            self::Pppoe => __('PPPoE'),
            self::PppProfiles => __('PPP Profile'),
            self::PppSecrets => __('PPP Secret'),
            self::PppActive => __('PPP Active'),
            self::Ftth => __('FTTH'),
            self::AreaCodes => __('Kode Area'),
            self::OdpOdc => __('ODP/ODC'),
            self::Onu => __('ONU/ONT'),
            self::Tickets => __('Tikets'),
            self::PsbTickets => __('PSB'),
            self::Billing => __('Billing'),
            self::BillingSetting => __('Setting'),
            self::BillingInvoices => __('Invoice'),
            self::BillingCustomers => __('Customer'),
            self::Whatsapp => __('WhatsApp'),
            self::WaTemplates => __('Template'),
            self::WaBroadcast => __('Broadcast'),
            self::BillingBills => __('Tagihan'),
            self::BillingReports => __('Laporan'),
            self::Servers => __('Servers'),
            self::Mikrotik => __('Mikrotik'),
            self::Radius => __('Radius'),
            self::Genieacs => __('GenieACS'),
            self::Gateways => __('Gateway'),
            self::Midtrans => __('Midtrans'),
            self::Waha => __('WAHA'),
            self::Users => __('Pengguna'),
            self::Roles => __('Role'),
            self::Permissions => __('Permission'),
        };
    }
}
