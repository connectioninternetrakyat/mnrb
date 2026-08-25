import { Head, router } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    formatDateTime,
    PsbStatusBadge,
} from '@/pages/tickets/psb/components/psb-shared';
import { index as psbIndex, show as psbShow } from '@/routes/tickets/psb';
import type { PsbTicketRow, PsbTicketStatus } from '@/types';

type PageProps = {
    tickets: {
        data: PsbTicketRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search?: string | null;
        status?: string | null;
    };
};

const statusOptions = ['terjadwal', 'dikerjakan', 'selesai', 'dibatalkan'];

const statusFilterLabels: Record<PsbTicketStatus, string> = {
    terjadwal: 'Terjadwal',
    dikerjakan: 'Dikerjakan',
    selesai: 'Selesai',
    dibatalkan: 'Dibatalkan',
};

export default function PsbTickets({ tickets, filters }: PageProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const submitFilter = (nextStatus?: string) => {
        router.get(
            psbIndex(),
            { search, status: nextStatus ?? status },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="Tiket PSB" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Tiket PSB"
                        description="Tiket pasang baru otomatis dibuat saat prospect disetujui."
                    />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitFilter();
                        }}
                        className="relative min-w-0 flex-1 sm:w-72 sm:flex-none"
                    >
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />

                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-8"
                            placeholder="Cari nomor tiket / pelanggan / teknisi..."
                        />
                    </form>

                    <Select
                        value={status || 'all'}
                        onValueChange={(value) => {
                            const nextStatus = value === 'all' ? '' : value;

                            setStatus(nextStatus);
                            submitFilter(nextStatus);
                        }}
                    >
                        <SelectTrigger className="w-[150px] shrink-0 sm:w-48">
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Status</SelectItem>
                            {statusOptions.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {
                                        statusFilterLabels[
                                            value as PsbTicketStatus
                                        ]
                                    }
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[880px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    No. Tiket
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Calon Pelanggan
                                </th>
                                <th className="px-4 py-3 font-medium">Paket</th>
                                <th className="px-4 py-3 font-medium">
                                    Teknisi
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Jadwal
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {tickets.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada tiket PSB.
                                    </td>
                                </tr>
                            )}

                            {tickets.data.map((ticket) => (
                                <tr key={ticket.id} className="border-t">
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <span className="font-mono font-medium">
                                            {ticket.ticket_number}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {ticket.prospect_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {ticket.area_code} ·{' '}
                                            {ticket.prospect_whatsapp}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {ticket.package_name || '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {ticket.assigned_to ?? '-'}
                                    </td>
                                    <td className="px-4 py-3 text-xs whitespace-nowrap">
                                        {formatDateTime(ticket.scheduled_at)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <PsbStatusBadge
                                            status={ticket.status}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.visit(psbShow(ticket.id))
                                            }
                                        >
                                            <Eye />
                                            Detail
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {tickets.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Halaman {tickets.current_page} dari{' '}
                            {tickets.last_page} ({tickets.total} tiket)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!tickets.links[0]?.url}
                                onClick={() =>
                                    tickets.links[0]?.url &&
                                    router.visit(tickets.links[0].url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Sebelumnya
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    !tickets.links[tickets.links.length - 1]
                                        ?.url
                                }
                                onClick={() =>
                                    tickets.links[tickets.links.length - 1]
                                        ?.url &&
                                    router.visit(
                                        tickets.links[tickets.links.length - 1]
                                            .url as string,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

PsbTickets.layout = {
    breadcrumbs: [
        {
            title: 'Tikets',
            href: '/tickets/psb',
        },
        {
            title: 'PSB',
            href: '/tickets/psb',
        },
    ],
};
