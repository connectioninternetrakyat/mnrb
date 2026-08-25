import { Link } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import {
    Activity,
    BookOpen,
    Cable,
    IdCard,
    KeySquare,
    CreditCard,
    Database,
    FolderGit2,
    KeyRound,
    LayoutGrid,
    Map,
    MapPinned,
    MessageCircle,
    MonitorSmartphone,
    Network,
    Package,
    RadioTower,
    Receipt,
    BarChart3,
    Megaphone,
    MessagesSquare,
    Router,
    Server,
    ShieldCheck,
    Ticket,
    Users,
    UsersRound,
    UserRoundPlus,
    Wallet,
    Wifi,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as billingBills } from '@/routes/billing/bill';
import { index as billingCustomer } from '@/routes/billing/customer';
import { index as billingInvoice } from '@/routes/billing/invoice';
import { index as billingReport } from '@/routes/billing/report';
import billingSetting from '@/routes/billing/setting';
import { index as ftthAreaCodes } from '@/routes/ftth/area-codes';
import { index as ftthOdpOdc } from '@/routes/ftth/odp-odc';
import { index as ftthOnu } from '@/routes/ftth/onu';
import { index as gatewaysMidtrans } from '@/routes/gateways/midtrans';
import { index as gatewaysWaha } from '@/routes/gateways/waha';
import { index as packagesIndex } from '@/routes/packages';
import { index as pelangganIndex } from '@/routes/pelanggan';
import { index as permissionsIndex } from '@/routes/permissions';
import { index as pppActives } from '@/routes/ppp/active';
import { index as pppProfiles } from '@/routes/ppp/profile';
import { index as pppSecrets } from '@/routes/ppp/secret';
import { index as prospectsIndex } from '@/routes/prospects';
import { index as rolesIndex } from '@/routes/roles';
import { index as serversGenieacs } from '@/routes/servers/genieacs';
import { index as serversMikrotik } from '@/routes/servers/mikrotik';
import { index as serversRadius } from '@/routes/servers/radius';
import { index as ticketsPsb } from '@/routes/tickets/psb';
import { index as usersIndex } from '@/routes/users';
import { index as waBroadcast } from '@/routes/wa/broadcast';
import { index as waTemplate } from '@/routes/wa/template';
import type { Auth, NavItem } from '@/types';

type PageProps = {
    auth: Auth;
};

export function AppSidebar() {
    const { auth } = usePage<PageProps>().props;

    const hasMenuPermission = (permission: string) =>
        auth.user.permissions.includes(permission);

    const pppoeItems: NavItem[] = [
        ...(hasMenuPermission('menus.ppp-profiles')
            ? [
                  {
                      title: 'PPP Profile',
                      href: pppProfiles(),
                      icon: IdCard,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.ppp-secrets')
            ? [
                  {
                      title: 'PPP Secret',
                      href: pppSecrets(),
                      icon: KeySquare,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.ppp-active')
            ? [
                  {
                      title: 'PPP Active',
                      href: pppActives(),
                      icon: Activity,
                  },
              ]
            : []),
    ];

    const ftthItems: NavItem[] = [
        ...(hasMenuPermission('menus.area-codes')
            ? [
                  {
                      title: 'Kode Area',
                      href: ftthAreaCodes(),
                      icon: Map,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.odp-odc')
            ? [
                  {
                      title: 'ODP/ODC',
                      href: ftthOdpOdc(),
                      icon: MapPinned,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.onu')
            ? [
                  {
                      title: 'ONU/ONT',
                      href: ftthOnu(),
                      icon: MonitorSmartphone,
                  },
              ]
            : []),
    ];

    const ticketItems: NavItem[] = [
        ...(hasMenuPermission('menus.tickets-psb')
            ? [
                  {
                      title: 'PSB',
                      href: ticketsPsb(),
                      icon: Ticket,
                  },
              ]
            : []),
    ];

    const billingItems: NavItem[] = [
        ...(hasMenuPermission('menus.billing-invoices')
            ? [
                  {
                      title: 'Invoice',
                      href: billingInvoice(),
                      icon: Receipt,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.billing-customers')
            ? [
                  {
                      title: 'Customer',
                      href: billingCustomer(),
                      icon: UsersRound,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.billing-reports')
            ? [
                  {
                      title: 'Laporan',
                      href: billingReport(),
                      icon: BarChart3,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.billing-bills')
            ? [
                  {
                      title: 'Tagihan',
                      href: billingBills(),
                      icon: Wallet,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.billing-setting')
            ? [
                  {
                      title: 'Setting',
                      href: billingSetting.index(),
                      icon: Wallet,
                  },
              ]
            : []),
    ];

    const waItems: NavItem[] = [
        ...(hasMenuPermission('menus.wa-templates')
            ? [
                  {
                      title: 'Template',
                      href: waTemplate(),
                      icon: MessagesSquare,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.wa-broadcast')
            ? [
                  {
                      title: 'Broadcast',
                      href: waBroadcast(),
                      icon: Megaphone,
                  },
              ]
            : []),
    ];

    const serverItems: NavItem[] = [
        ...(hasMenuPermission('menus.mikrotik')
            ? [
                  {
                      title: 'Mikrotik',
                      href: serversMikrotik(),
                      icon: Router,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.radius')
            ? [
                  {
                      title: 'Radius',
                      href: serversRadius(),
                      icon: Database,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.genieacs')
            ? [
                  {
                      title: 'GenieACS',
                      href: serversGenieacs(),
                      icon: Wifi,
                  },
              ]
            : []),
    ];

    const gatewayItems: NavItem[] = [
        ...(hasMenuPermission('menus.midtrans')
            ? [
                  {
                      title: 'Midtrans',
                      href: gatewaysMidtrans(),
                      icon: CreditCard,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.waha')
            ? [
                  {
                      title: 'WAHA',
                      href: gatewaysWaha(),
                      icon: MessageCircle,
                  },
              ]
            : []),
    ];

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(hasMenuPermission('menus.pelanggans')
            ? [
                  {
                      title: 'Pelanggan',
                      href: pelangganIndex(),
                      icon: Users,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.prospects')
            ? [
                  {
                      title: 'Prospect',
                      href: prospectsIndex(),
                      icon: UserRoundPlus,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.packages')
            ? [
                  {
                      title: 'Paket & Harga',
                      href: packagesIndex(),
                      icon: Package,
                  },
              ]
            : []),
        ...(pppoeItems.length > 0
            ? [
                  {
                      title: 'PPPoE',
                      href: pppoeItems[0].href,
                      icon: RadioTower,
                      items: pppoeItems,
                  },
              ]
            : []),
        ...(ftthItems.length > 0
            ? [
                  {
                      title: 'FTTH',
                      href: ftthItems[0].href,
                      icon: Network,
                      items: ftthItems,
                  },
              ]
            : []),
        ...(ticketItems.length > 0
            ? [
                  {
                      title: 'Tikets',
                      href: ticketItems[0].href,
                      icon: Ticket,
                      items: ticketItems,
                  },
              ]
            : []),
        ...(billingItems.length > 0
            ? [
                  {
                      title: 'Billing',
                      href: billingItems[0].href,
                      icon: Wallet,
                      items: billingItems,
                  },
              ]
            : []),
        ...(waItems.length > 0
            ? [
                  {
                      title: 'WhatsApp',
                      href: waItems[0].href,
                      icon: MessagesSquare,
                      items: waItems,
                  },
              ]
            : []),
        ...(serverItems.length > 0
            ? [
                  {
                      title: 'Servers',
                      href: serverItems[0].href,
                      icon: Server,
                      items: serverItems,
                  },
              ]
            : []),
        ...(gatewayItems.length > 0
            ? [
                  {
                      title: 'Gateway',
                      href: gatewayItems[0].href,
                      icon: Cable,
                      items: gatewayItems,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.users')
            ? [
                  {
                      title: 'Pengguna',
                      href: usersIndex(),
                      icon: UsersRound,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.roles')
            ? [
                  {
                      title: 'Role',
                      href: rolesIndex(),
                      icon: ShieldCheck,
                  },
              ]
            : []),
        ...(hasMenuPermission('menus.permissions')
            ? [
                  {
                      title: 'Permission',
                      href: permissionsIndex(),
                      icon: KeyRound,
                  },
              ]
            : []),
    ];

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
