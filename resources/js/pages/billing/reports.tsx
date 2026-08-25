import { Head, router } from '@inertiajs/react';
import {
    ArrowDownToLine,
    Banknote,
    FileWarning,
    TrendingUp,
    Users,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    index as reportIndex,
    download as reportDownload,
} from '@/routes/billing/report';

type MonthRow = {
    month: number;
    label: string;
    invoice_count: number;
    invoice_total: number;
    bill_count: number;
    bill_total: number;
    total: number;
};

type Cards = {
    activation_total: number;
    activation_count: number;
    bill_total: number;
    bill_count: number;
    income_total: number;
};

const rupiah = (value: number) =>
    `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;

export default function FinancialReports({
    year,
    years,
    months,
    cards,
    outstanding,
    activeCustomers,
    grand,
}: {
    year: number;
    years: number[];
    months: MonthRow[];
    cards: Cards;
    outstanding: { total: number; count: number };
    activeCustomers: number;
    grand: {
        invoice_count: number;
        invoice_total: number;
        bill_count: number;
        bill_total: number;
        total: number;
    };
}) {
    const changeYear = (value: string) => {
        router.visit(reportIndex({ query: { year: value } }), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Laporan Keuangan" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Laporan Keuangan"
                        description="Rekap pendapatan aktivasi dan tagihan bulanan dari data nyata."
                    />

                    <div className="flex items-center gap-2">
                        <Select value={String(year)} onValueChange={changeYear}>
                            <SelectTrigger className="w-28">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {years.map((option) => (
                                    <SelectItem
                                        key={option}
                                        value={String(option)}
                                    >
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Button
                            variant="outline"
                            onClick={() =>
                                window.open(
                                    reportDownload.url({
                                        query: { year: String(year) },
                                    }),
                                    '_blank',
                                )
                            }
                        >
                            <ArrowDownToLine />
                            Unduh CSV
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={Banknote}
                        label="Pendapatan Aktivasi (bulan ini)"
                        value={rupiah(cards.activation_total)}
                        sub={`${cards.activation_count} invoice lunas`}
                    />
                    <StatCard
                        icon={TrendingUp}
                        label="Pendapatan Tagihan (bulan ini)"
                        value={rupiah(cards.bill_total)}
                        sub={`${cards.bill_count} tagihan lunas`}
                    />
                    <StatCard
                        icon={FileWarning}
                        label="Tunggakan"
                        value={rupiah(outstanding.total)}
                        sub={`${outstanding.count} tagihan belum bayar`}
                    />
                    <StatCard
                        icon={Users}
                        label="Pelanggan Aktif"
                        value={String(activeCustomers)}
                        sub="pelanggan FTTH"
                    />
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Bulan</th>
                                <th className="px-4 py-3 font-medium">
                                    Invoice Lunas
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Pendapatan Aktivasi
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Tagihan Lunas
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Pendapatan Tagihan
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {months.map((month) => (
                                <tr key={month.month} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {month.label}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {month.invoice_count}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {rupiah(month.invoice_total)}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {month.bill_count}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {rupiah(month.bill_total)}
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold tabular-nums">
                                        {rupiah(month.total)}
                                    </td>
                                </tr>
                            ))}

                            <tr className="border-t-2 bg-muted/40 font-semibold">
                                <td className="px-4 py-3">Total {year}</td>
                                <td className="px-4 py-3 tabular-nums">
                                    {grand.invoice_count}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {rupiah(grand.invoice_total)}
                                </td>
                                <td className="px-4 py-3 tabular-nums">
                                    {grand.bill_count}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {rupiah(grand.bill_total)}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {rupiah(grand.total)}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p className="text-xs text-muted-foreground">
                    <Badge variant="outline">catatan</Badge> Angka diambil
                    langsung dari invoice &amp; tagihan berstatus lunas pada
                    tahun {year}. Tunggakan mencakup seluruh tagihan bulanan
                    yang belum dibayar.
                </p>
            </div>
        </>
    );
}

function StatCard({
    icon: Icon,
    label,
    value,
    sub,
}: {
    icon: typeof Banknote;
    label: string;
    value: string;
    sub: string;
}) {
    return (
        <div className="space-y-1 rounded-xl border p-4">
            <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                <Icon className="size-4" />
                {label}
            </div>
            <p className="text-xl font-bold tabular-nums">{value}</p>
            <p className="text-xs text-muted-foreground">{sub}</p>
        </div>
    );
}
