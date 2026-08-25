import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    FileWarning,
    RadioTower,
    TrendingUp,
    Users,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { index as billIndex } from '@/routes/billing/bill';
import { index as prospectsIndex } from '@/routes/prospects';
import { index as psbIndex } from '@/routes/tickets/psb';

type Stat = { label: string; value: string; sub: string; icon: typeof Users };

const rupiah = (value: number) =>
    `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;

const formatDate = (value: string | null) =>
    value === null
        ? '-'
        : new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'short',
          }).format(new Date(value));

export default function Dashboard({
    stats,
    latestProspects,
    latestTickets,
    dueBills,
}: {
    stats: Record<string, number | null>;
    latestProspects: {
        id: number;
        name: string;
        whatsapp: string;
        area_code: string | null;
        created_at: string | null;
    }[];
    latestTickets: {
        id: number;
        ticket_number: string;
        customer_name: string | null;
        status: 'terjadwal' | 'dikerjakan' | 'selesai' | 'dibatalkan';
        scheduled_at: string | null;
    }[];
    dueBills: {
        id: number;
        bill_number: string;
        period: string;
        total: number;
        customer_name: string | null;
    }[];
}) {
    const cards: Stat[] = [
        {
            label: 'Pelanggan Aktif',
            value: String(stats.pelanggan_aktif ?? 0),
            sub: `dari ${stats.pelanggan_total ?? 0} total pelanggan`,
            icon: Users,
        },
        {
            label: 'PPPoE Online',
            value:
                stats.pppoe_online === null ? '—' : String(stats.pppoe_online),
            sub: 'sesi RADIUS aktif',
            icon: RadioTower,
        },
        {
            label: 'Pendapatan Bulan Ini',
            value: rupiah(stats.pendapatan_bulan_ini ?? 0),
            sub: 'aktivasi + tagihan lunas',
            icon: TrendingUp,
        },
        {
            label: 'Tunggakan',
            value: rupiah(stats.tunggakan_total ?? 0),
            sub: `${stats.tagihan_belum_bayar ?? 0} tagihan belum bayar`,
            icon: FileWarning,
        },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <Heading
                    variant="small"
                    title="Dashboard"
                    description="Ringkasan operasional jaringan & billing Anda secara nyata."
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <div
                            key={card.label}
                            className="space-y-1 rounded-xl border p-4"
                        >
                            <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                <card.icon className="size-4" />
                                {card.label}
                            </div>
                            <p className="text-xl font-bold tabular-nums">
                                {card.value}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {card.sub}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Panel
                        title="Prospek Menunggu Verifikasi"
                        action={{ label: 'Kelola', href: prospectsIndex().url }}
                    >
                        {latestProspects.length === 0 && (
                            <Empty text="Tidak ada prospek menunggu." />
                        )}

                        {latestProspects.map((prospect) => (
                            <LinkRow
                                key={prospect.id}
                                href={prospectsIndex().url}
                                title={prospect.name}
                                sub={`${prospect.area_code ?? '-'} · ${prospect.whatsapp}`}
                            />
                        ))}
                    </Panel>

                    <Panel
                        title="Tiket PSB Terbaru"
                        action={{ label: 'Semua Tiket', href: psbIndex().url }}
                    >
                        {latestTickets.length === 0 && (
                            <Empty text="Belum ada tiket." />
                        )}

                        {latestTickets.map((ticket) => (
                            <LinkRow
                                key={ticket.id}
                                href={psbIndex().url}
                                title={ticket.ticket_number}
                                sub={`${ticket.customer_name ?? '-'} · ${formatDate(ticket.scheduled_at)}`}
                                badge={
                                    ticket.status === 'terjadwal'
                                        ? 'Terjadwal'
                                        : ticket.status === 'dikerjakan'
                                          ? 'Dikerjakan'
                                          : ticket.status === 'selesai'
                                            ? 'Selesai'
                                            : 'Dibatalkan'
                                }
                            />
                        ))}
                    </Panel>

                    <Panel
                        title="Tagihan Belum Dibayar"
                        action={{ label: 'Kelola', href: billIndex().url }}
                    >
                        {dueBills.length === 0 && (
                            <Empty text="Tidak ada tunggakan." />
                        )}

                        {dueBills.map((bill) => (
                            <LinkRow
                                key={bill.id}
                                href={billIndex().url}
                                title={bill.customer_name ?? '-'}
                                sub={`${bill.bill_number} · ${bill.period}`}
                                badge={rupiah(bill.total)}
                            />
                        ))}
                    </Panel>
                </div>
            </div>
        </>
    );
}

function Panel({
    title,
    action,
    children,
}: {
    title: string;
    action: { label: string; href: string };
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col rounded-xl border">
            <div className="flex items-center justify-between gap-2 border-b px-4 py-3">
                <h3 className="text-sm font-semibold">{title}</h3>

                <Link
                    href={action.href}
                    className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                >
                    {action.label}
                    <ArrowRight className="size-3.5" />
                </Link>
            </div>

            <div className="divide-y">{children}</div>
        </div>
    );
}

function Empty({ text }: { text: string }) {
    return (
        <p className="px-4 py-6 text-center text-sm text-muted-foreground">
            {text}
        </p>
    );
}

function LinkRow({
    href,
    title,
    sub,
    badge,
}: {
    href: string;
    title: string;
    sub: string;
    badge?: string;
}) {
    return (
        <Link
            href={href}
            className="flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-muted/50"
        >
            <span className="min-w-0">
                <span className="block truncate text-sm font-medium">
                    {title}
                </span>
                <span className="block truncate text-xs text-muted-foreground">
                    {sub}
                </span>
            </span>
            {badge && <Badge variant="outline">{badge}</Badge>}
        </Link>
    );
}
