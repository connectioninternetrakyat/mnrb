import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ReceiptText, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as billingCustomerIndex } from '@/routes/billing/customer';

type DetailCustomer = {
    id: number;
    name: string;
    email: string | null;
    whatsapp: string;
    address: string;
    status: 'aktif' | 'ditangguhkan';
    area_code: string | null;
    area_name: string | null;
    package_name: string | null;
    package_price: number | null;
    ppp_profile: string | null;
    secret_username: string | null;
    secret_disabled: boolean;
    notes: string | null;
    converted_from_prospect: boolean;
    pelanggan_id: number | null;
    pelanggan_name: string | null;
};

type TicketInfo = {
    id: number;
    ticket_number: string;
    status: string;
    technician: string | null;
    finished_at: string | null;
    isolated: boolean;
};

type ProspectInfo = {
    id: number;
    name: string;
    email: string | null;
    whatsapp: string;
    address: string;
    status: string;
};

type InvoiceInfo = {
    invoice_number: string;
    payment_method: string | null;
    bank: string | null;
    va_number: string | null;
    status: string;
    total: number;
    paid_at: string | null;
    created_at: string | null;
};

type BillInfo = {
    bill_number: string;
    period: string;
    monthly_price: number;
    dpp: number;
    ppn: number;
    total: number;
    status: string;
    paid_at: string | null;
};

type PageProps = {
    customer: DetailCustomer;
    ticket: TicketInfo | null;
    invoice: InvoiceInfo | null;
    bills: BillInfo[];
    summary: {
        outstanding: number;
        unpaid_count: number;
        bills_count: number;
    };
};

const rupiah = (value: number) =>
    `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;

const formatDateTime = (value: string | null) =>
    value
        ? new Date(value).toLocaleString('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '-';

function Panel({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-3 rounded-xl border p-4">
            <h3 className="text-sm font-semibold">{title}</h3>
            {children}
        </div>
    );
}

function InfoRow({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{children}</span>
        </div>
    );
}

export default function BillingCustomerDetail({
    customer,
    ticket,
    invoice,
    bills,
    summary,
}: PageProps) {
    return (
        <>
            <Head title={`Customer ${customer.name}`} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={customer.name}
                        description="Detail lengkap customer billing terintegrasi tiket PSB, invoice dan tagihan."
                    />

                    <Button
                        variant="outline"
                        onClick={() => router.visit(billingCustomerIndex())}
                    >
                        <ArrowLeft />
                        Daftar Customer
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Panel title="Identitas">
                        <InfoRow label="Status">
                            {customer.status === 'aktif' ? (
                                <Badge>Aktif</Badge>
                            ) : (
                                <Badge variant="secondary">Ditangguhkan</Badge>
                            )}
                            {ticket?.isolated && (
                                <Badge variant="destructive" className="ml-2">
                                    Terisolir
                                </Badge>
                            )}
                        </InfoRow>
                        <InfoRow label="WhatsApp">
                            {customer.whatsapp}
                        </InfoRow>
                        <InfoRow label="Email">
                            {customer.email || '-'}
                        </InfoRow>
                        <InfoRow label="Alamat">
                            {customer.address || '-'}
                        </InfoRow>
                        <InfoRow label="Area">
                            {customer.area_code
                                ? `${customer.area_code} - ${customer.area_name}`
                                : '-'}
                        </InfoRow>
                        {customer.notes && (
                            <InfoRow label="Catatan">
                                {customer.notes}
                            </InfoRow>
                        )}
                    </Panel>

                    <Panel title="Layanan">
                        <InfoRow label="Paket">
                            {customer.package_name || '-'}
                        </InfoRow>
                        <InfoRow label="Harga / bulan">
                            {customer.package_price !== null
                                ? rupiah(customer.package_price)
                                : '-'}
                        </InfoRow>
                        <InfoRow label="Profil PPP">
                            {customer.ppp_profile || '-'}
                        </InfoRow>
                        <InfoRow label="Username PPPoE">
                            {customer.secret_username ? (
                                <span className="font-mono text-xs">
                                    {customer.secret_username}
                                    {customer.secret_disabled && (
                                        <Badge
                                            variant="destructive"
                                            className="ml-2"
                                        >
                                            disabled
                                        </Badge>
                                    )}
                                </span>
                            ) : (
                                '-'
                            )}
                        </InfoRow>
                    </Panel>

                    <Panel title="Integrasi Tiket PSB & Pelanggan FTTH">
                        {ticket ? (
                            <>
                                <InfoRow label="No. Tiket">
                                    <span className="font-mono text-xs">
                                        {ticket.ticket_number}
                                    </span>
                                </InfoRow>
                                <InfoRow label="Status Tiket">
                                    <Badge variant="outline">
                                        {ticket.status}
                                    </Badge>
                                </InfoRow>
                                <InfoRow label="Teknisi">
                                    {ticket.technician ?? '-'}
                                </InfoRow>
                                <InfoRow label="Selesai Pasang">
                                    {formatDateTime(ticket.finished_at)}
                                </InfoRow>
                                {customer.pelanggan_id && (
                                    <InfoRow label="Pelanggan FTTH">
                                        <Button
                                            variant="link"
                                            size="sm"
                                            className="h-auto p-0"
                                            onClick={() =>
                                                router.visit(
                                                    `/pelanggan/${customer.pelanggan_id}`,
                                                )
                                            }
                                        >
                                            <Users className="size-3.5" />
                                            {customer.pelanggan_name ??
                                                'Buka detail pelanggan'}
                                        </Button>
                                    </InfoRow>
                                )}
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Customer manual — tidak tertaut tiket PSB.
                            </p>
                        )}
                    </Panel>

                    <Panel title="Invoice Aktivasi">
                        {invoice ? (
                            <>
                                <InfoRow label="No. Invoice">
                                    <span className="font-mono text-xs">
                                        {invoice.invoice_number}
                                    </span>
                                </InfoRow>
                                <InfoRow label="Total">
                                    {rupiah(invoice.total)}
                                </InfoRow>
                                <InfoRow label="Status">
                                    <Badge
                                        variant={
                                            invoice.status === 'lunas'
                                                ? 'default'
                                                : 'destructive'
                                        }
                                    >
                                        {invoice.status === 'lunas'
                                            ? 'Lunas'
                                            : 'Menunggu Pembayaran'}
                                    </Badge>
                                </InfoRow>
                                <InfoRow label="Metode">
                                    {invoice.payment_method === 'transfer'
                                        ? `Transfer ${invoice.bank ?? ''}`.trim()
                                        : 'Tunai'}
                                </InfoRow>
                                {invoice.va_number && (
                                    <InfoRow label="VA">
                                        <span className="font-mono text-xs">
                                            {invoice.va_number}
                                        </span>
                                    </InfoRow>
                                )}
                                <InfoRow label="Dibayar">
                                    {formatDateTime(invoice.paid_at)}
                                </InfoRow>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Belum ada invoice aktivasi.
                            </p>
                        )}
                    </Panel>
                </div>

                <div className="space-y-3 rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 className="flex items-center gap-2 text-sm font-semibold">
                            <ReceiptText className="size-4" />
                            Tagihan Bulanan ({summary.bills_count})
                        </h3>
                        <div className="flex items-center gap-2 text-xs">
                            {summary.unpaid_count > 0 ? (
                                <Badge variant="destructive">
                                    Tunggakan {rupiah(summary.outstanding)} (
                                    {summary.unpaid_count} tagihan)
                                </Badge>
                            ) : (
                                <Badge>Tidak ada tunggakan</Badge>
                            )}
                        </div>
                    </div>

                    {bills.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            Belum ada tagihan bulanan.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            No. Tagihan
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Periode
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Harga
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            DPP
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            PPN
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Total
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Dibayar
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {bills.map((bill) => (
                                        <tr
                                            key={bill.bill_number}
                                            className="border-t"
                                        >
                                            <td className="px-3 py-2 font-mono text-xs">
                                                {bill.bill_number}
                                            </td>
                                            <td className="px-3 py-2">
                                                {bill.period}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {rupiah(bill.monthly_price)}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {rupiah(bill.dpp)}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {rupiah(bill.ppn)}
                                            </td>
                                            <td className="px-3 py-2 font-medium whitespace-nowrap">
                                                {rupiah(bill.total)}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge
                                                    variant={
                                                        bill.status === 'lunas'
                                                            ? 'default'
                                                            : 'destructive'
                                                    }
                                                >
                                                    {bill.status === 'lunas'
                                                        ? 'Lunas'
                                                        : 'Menunggu'}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2 text-xs text-muted-foreground">
                                                {formatDateTime(bill.paid_at)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
