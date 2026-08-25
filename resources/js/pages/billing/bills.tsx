import { Head, router, usePage } from '@inertiajs/react';
import { BadgeCheck, RefreshCw, Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/pages/tickets/psb/components/psb-shared';
import {
    generate as billGenerate,
    index as billIndex,
    markPaid as billMarkPaid,
} from '@/routes/billing/bill';
import type { Auth, BillRow } from '@/types';

type PageProps = {
    bills: {
        data: BillRow[];
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

const statusLabels: Record<BillRow['status'], string> = {
    menunggu_pembayaran: 'Menunggu Pembayaran',
    lunas: 'Lunas',
};

const rupiah = (value: number) =>
    `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;

export default function Bills({ bills, filters }: PageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;

    const canUpdate = auth.user.permissions.includes('billing.update');

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [billToPay, setBillToPay] = useState<BillRow | null>(null);

    const submitFilter = (nextStatus?: string) => {
        router.get(
            billIndex(),
            { search, status: nextStatus ?? status },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="Tagihan" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Tagihan"
                        description="Tagihan bulanan pelanggan aktif sesuai harga paketnya."
                    />

                    {canUpdate && (
                        <Button
                            onClick={() =>
                                router.post(billGenerate(), undefined, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <RefreshCw />
                            Generate Tagihan Bulan Ini
                        </Button>
                    )}
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
                            placeholder="Cari no. tagihan / pelanggan..."
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
                        <SelectTrigger className="w-[170px] shrink-0 sm:w-56">
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Status</SelectItem>
                            {Object.entries(statusLabels).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[960px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    No. Tagihan
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Periode
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Pelanggan
                                </th>
                                <th className="px-4 py-3 font-medium">Paket</th>
                                <th className="px-4 py-3 font-medium">DPP</th>
                                <th className="px-4 py-3 font-medium">PPN</th>
                                <th className="px-4 py-3 font-medium">Total</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {bills.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada tagihan. Klik "Generate
                                        Tagihan Bulan Ini" untuk membuat.
                                    </td>
                                </tr>
                            )}

                            {bills.data.map((bill) => (
                                <tr key={bill.id} className="border-t">
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <span className="font-mono font-medium">
                                            {bill.bill_number}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {bill.period}
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {bill.customer_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {bill.area_code} ·{' '}
                                            {bill.customer_whatsapp}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {bill.package_name || '-'}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {rupiah(bill.dpp)}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {rupiah(bill.ppn)}
                                    </td>
                                    <td className="px-4 py-3 font-semibold whitespace-nowrap tabular-nums">
                                        {rupiah(bill.total)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                bill.status === 'lunas'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                            className={
                                                bill.status === 'lunas'
                                                    ? 'bg-emerald-600'
                                                    : undefined
                                            }
                                        >
                                            {statusLabels[bill.status]}
                                        </Badge>
                                        {bill.paid_at && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {formatDateTime(bill.paid_at)}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {canUpdate &&
                                            bill.status ===
                                                'menunggu_pembayaran' && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setBillToPay(bill)
                                                    }
                                                >
                                                    <BadgeCheck />
                                                    Tandai Lunas
                                                </Button>
                                            )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {bills.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Halaman {bills.current_page} dari {bills.last_page}{' '}
                            ({bills.total} tagihan)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!bills.links[0]?.url}
                                onClick={() =>
                                    bills.links[0]?.url &&
                                    router.visit(bills.links[0].url, {
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
                                    !bills.links[bills.links.length - 1]?.url
                                }
                                onClick={() =>
                                    bills.links[bills.links.length - 1]?.url &&
                                    router.visit(
                                        bills.links[bills.links.length - 1]
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

            <Dialog
                open={billToPay !== null}
                onOpenChange={(open) => !open && setBillToPay(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tandai lunas?</DialogTitle>
                        <DialogDescription>
                            Tagihan{' '}
                            <span className="font-medium">
                                {billToPay?.bill_number}
                            </span>{' '}
                            atas nama{' '}
                            <span className="font-medium">
                                {billToPay?.customer_name}
                            </span>{' '}
                            senilai{' '}
                            <span className="font-semibold">
                                {billToPay && rupiah(billToPay.total)}
                            </span>{' '}
                            akan ditandai lunas.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Batal</Button>
                        </DialogClose>

                        <Button
                            onClick={() =>
                                billToPay &&
                                router.post(
                                    billMarkPaid(billToPay.id),
                                    undefined,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setBillToPay(null),
                                    },
                                )
                            }
                        >
                            <BadgeCheck />
                            Ya, Lunas
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
