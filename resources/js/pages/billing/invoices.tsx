import { Head, router, usePage } from '@inertiajs/react';
import { BadgeCheck, Search } from 'lucide-react';
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
    index as invoiceIndex,
    markPaid as invoiceMarkPaid,
} from '@/routes/billing/invoice';
import type { Auth, InvoiceRow } from '@/types';

type PageProps = {
    invoices: {
        data: InvoiceRow[];
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

const statusLabels: Record<InvoiceRow['status'], string> = {
    menunggu_pembayaran: 'Menunggu Pembayaran',
    lunas: 'Lunas',
};

const rupiah = (value: number) =>
    `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;

export default function Invoices({ invoices, filters }: PageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;

    const canUpdate = auth.user.permissions.includes('billing.update');

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [invoiceToPay, setInvoiceToPay] = useState<InvoiceRow | null>(null);

    const submitFilter = (nextStatus?: string) => {
        router.get(
            invoiceIndex(),
            { search, status: nextStatus ?? status },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="Invoice" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Invoice"
                        description="Tagihan aktivasi PSB. Lunas tunai otomatis; transfer terverifikasi via Midtrans."
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
                            placeholder="Cari no. invoice / pelanggan / VA..."
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
                                    No. Invoice
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Pelanggan
                                </th>
                                <th className="px-4 py-3 font-medium">Paket</th>
                                <th className="px-4 py-3 font-medium">
                                    Pembayaran
                                </th>
                                <th className="px-4 py-3 font-medium">Total</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Diterbitkan
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada invoice.
                                    </td>
                                </tr>
                            )}

                            {invoices.data.map((invoice) => (
                                <tr key={invoice.id} className="border-t">
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <span className="font-mono font-medium">
                                            {invoice.invoice_number}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {invoice.customer_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {invoice.customer_whatsapp}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {invoice.package_name || '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {invoice.payment_method === 'tunai' ? (
                                            'Tunai'
                                        ) : (
                                            <>
                                                Transfer{' '}
                                                <Badge variant="outline">
                                                    {invoice.bank?.toUpperCase()}
                                                </Badge>
                                                {invoice.va_number && (
                                                    <p className="mt-0.5 font-mono text-xs text-muted-foreground tabular-nums">
                                                        {invoice.va_number}
                                                    </p>
                                                )}
                                            </>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-semibold whitespace-nowrap tabular-nums">
                                        {rupiah(
                                            invoice.payment_method === 'tunai'
                                                ? invoice.cash_total
                                                : invoice.total_exact,
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                invoice.status === 'lunas'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                            className={
                                                invoice.status === 'lunas'
                                                    ? 'bg-emerald-600'
                                                    : undefined
                                            }
                                        >
                                            {statusLabels[invoice.status]}
                                        </Badge>
                                        {invoice.paid_at && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {formatDateTime(
                                                    invoice.paid_at,
                                                )}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-xs whitespace-nowrap text-muted-foreground">
                                        {formatDateTime(invoice.created_at)}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {canUpdate &&
                                            invoice.status ===
                                                'menunggu_pembayaran' && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setInvoiceToPay(invoice)
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

                {invoices.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Halaman {invoices.current_page} dari{' '}
                            {invoices.last_page} ({invoices.total} invoice)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!invoices.links[0]?.url}
                                onClick={() =>
                                    invoices.links[0]?.url &&
                                    router.visit(invoices.links[0].url, {
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
                                    !invoices.links[invoices.links.length - 1]
                                        ?.url
                                }
                                onClick={() =>
                                    invoices.links[invoices.links.length - 1]
                                        ?.url &&
                                    router.visit(
                                        invoices.links[
                                            invoices.links.length - 1
                                        ].url as string,
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
                open={invoiceToPay !== null}
                onOpenChange={(open) => !open && setInvoiceToPay(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tandai lunas?</DialogTitle>
                        <DialogDescription>
                            Invoice{' '}
                            <span className="font-medium">
                                {invoiceToPay?.invoice_number}
                            </span>{' '}
                            atas nama{' '}
                            <span className="font-medium">
                                {invoiceToPay?.customer_name}
                            </span>{' '}
                            senilai{' '}
                            <span className="font-semibold">
                                {invoiceToPay &&
                                    rupiah(invoiceToPay.total_exact)}
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
                                invoiceToPay &&
                                router.post(
                                    invoiceMarkPaid(invoiceToPay.id),
                                    undefined,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setInvoiceToPay(null),
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
