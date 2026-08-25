import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, MapPin, ReceiptText } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as pelangganIndex } from '@/routes/pelanggan';

type DetailPelanggan = {
    id: number;
    name: string;
    email: string | null;
    whatsapp: string;
    address: string;
    maps_url: string | null;
    status: 'aktif' | 'berhenti';
    area_code: string | null;
    area_name: string | null;
    package_name: string | null;
    package_price: number | null;
    ppp_profile: string | null;
    secret_username: string | null;
    secret_disabled: boolean;
    joined_at: string | null;
    activated_at: string | null;
    notes: string | null;
};

type TicketInfo = {
    id: number;
    ticket_number: string;
    status: string;
    technician: string | null;
    scheduled_at: string | null;
    started_at: string | null;
    finished_at: string | null;
    cancelled_at: string | null;
    isolated: boolean;
    isolated_profile: string | null;
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
    pelanggan: DetailPelanggan;
    ticket: TicketInfo | null;
    prospect: ProspectInfo | null;
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

export default function PelangganDetail({
    pelanggan,
    ticket,
    prospect,
    invoice,
    bills,
    summary,
}: PageProps) {
    const statusBadge =
        pelanggan.status === 'aktif' ? (
            <Badge>Aktif</Badge>
        ) : (
            <Badge variant="secondary">Berhenti</Badge>
        );

    return (
        <>
            <Head title={`Pelanggan ${pelanggan.name}`} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={pelanggan.name}
                        description="Detail lengkap pelanggan FTTH terintegrasi prospek, tiket PSB dan billing."
                    />

                    <Button
                        variant="outline"
                        onClick={() => router.visit(pelangganIndex())}
                    >
                        <ArrowLeft />
                        Daftar Pelanggan
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Panel title="Identitas">
                        <InfoRow label="Status">
                            {statusBadge}
                            {ticket?.isolated && (
                                <Badge
                                    variant="destructive"
                                    className="ml-2"
                                >
                                    Terisolir
                                </Badge>
                            )}
                        </InfoRow>
                        <InfoRow label="WhatsApp">
                            {pelanggan.whatsapp}
                        </InfoRow>
                        <InfoRow label="Email">
                            {pelanggan.email || '-'}
                        </InfoRow>
                        <InfoRow label="Alamat">
                            {pelanggan.address || '-'}
                        </InfoRow>
                        <InfoRow label="Lokasi Google Maps">
                            {pelanggan.maps_url ? (
                                <a
                                    href={pelanggan.maps_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    <MapPin className="size-3.5" />
                                    Buka Lokasi
                                    <ExternalLink className="size-3.5" />
                                </a>
                            ) : (
                                '-'
                            )}
                        </InfoRow>
                        <InfoRow label="Area">
                            {pelanggan.area_code
                                ? `${pelanggan.area_code} - ${pelanggan.area_name}`
                                : '-'}
                        </InfoRow>
                        <InfoRow label="Bergabung">
                            {formatDateTime(pelanggan.joined_at)}
                        </InfoRow>
                        <InfoRow label="Aktivasi">
                            {formatDateTime(pelanggan.activated_at)}
                        </InfoRow>
                        {pelanggan.notes && (
                            <InfoRow label="Catatan">
                                {pelanggan.notes}
                            </InfoRow>
                        )}
                    </Panel>

                    <Panel title="Layanan & Akun PPPoE">
                        <InfoRow label="Paket">
                            {pelanggan.package_name || '-'}
                        </InfoRow>
                        <InfoRow label="Harga / bulan">
                            {pelanggan.package_price !== null
                                ? rupiah(pelanggan.package_price)
                                : '-'}
                        </InfoRow>
                        <InfoRow label="Profil PPP">
                            {pelanggan.ppp_profile || '-'}
                        </InfoRow>
                        <InfoRow label="Username PPPoE">
                            {pelanggan.secret_username ? (
                                <span className="font-mono text-xs">
                                    {pelanggan.secret_username}
                                    {pelanggan.secret_disabled && (
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

                    <Panel title="Asal Prospek & Tiket PSB">
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
                                <InfoRow label="Jadwal">
                                    {formatDateTime(ticket.scheduled_at)}
                                </InfoRow>
                                <InfoRow label="Selesai">
                                    {formatDateTime(ticket.finished_at)}
                                </InfoRow>
                                {prospect && (
                                    <>
                                        <InfoRow label="Prospek Asal">
                                            {prospect.name} ({prospect.whatsapp})
                                        </InfoRow>
                                    </>
                                )}
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Pelanggan manual — tidak berasal dari prospek.
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
