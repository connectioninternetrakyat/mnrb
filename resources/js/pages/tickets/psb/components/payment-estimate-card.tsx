import { Badge } from '@/components/ui/badge';
import type { InvoiceEstimate } from '@/types';

const rupiah = (value: number) =>
    `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));

export function PaymentEstimateCard({
    estimate,
}: {
    estimate: InvoiceEstimate;
}) {
    const rows: { label: string; value: string; muted?: boolean }[] = [
        {
            label: 'Paket (bulanan)',
            value: rupiah(estimate.monthly_price),
        },
        {
            label: `Prorata (${estimate.active_days}/${estimate.period_days} hari)`,
            value: rupiah(estimate.prorata),
        },
        {
            label: 'Biaya pasang (OTC)',
            value: rupiah(estimate.otc_fee),
            muted: true,
        },
        {
            label: `Diskon promo ${estimate.promo_percent}%`,
            value: `−${rupiah(estimate.discount)}`,
            muted: true,
        },
    ];

    return (
        <div className="max-w-2xl space-y-3 rounded-xl border p-4">
            <div className="space-y-1">
                <h3 className="flex items-center gap-2 text-sm font-semibold">
                    Pembayaran
                    <Badge variant="secondary">estimasi</Badge>
                </h3>
                <p className="text-xs text-muted-foreground">
                    Tagihan aktivasi &amp; biaya pasang (OTC)
                </p>
                <p className="text-xs text-muted-foreground">
                    Estimasi (prorata + OTC + PPN) — invoice sesungguhnya terbit
                    otomatis saat teknisi menutup finishing.
                </p>
            </div>

            <div className="space-y-1.5 rounded-lg bg-muted/40 p-3">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Prorata
                </p>

                {rows.map((row) => (
                    <PaymentRow key={row.label} {...row} />
                ))}

                <PaymentRow
                    label="Subtotal (DPP)"
                    value={rupiah(estimate.dpp)}
                    strong
                />
                <PaymentRow label="PPN" value={rupiah(estimate.ppn)} />
                <PaymentRow
                    label="Pembulatan (cash)"
                    value={rupiah(estimate.cash_rounding)}
                />

                <div className="flex items-center justify-between border-t pt-2">
                    <span className="text-sm font-semibold">Total</span>
                    <span className="text-sm font-bold">
                        {rupiah(estimate.cash_total)}
                    </span>
                </div>

                <p className="pt-1 text-xs text-muted-foreground">
                    Pembulatan ke Rp1.000 hanya berlaku bila dibayar cash —
                    tagihan online/VA ditagih exact{' '}
                    <span className="font-medium text-foreground">
                        {rupiah(estimate.total_exact)}
                    </span>
                    .
                </p>
            </div>

            <div className="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                <span className="font-medium">Periode</span>
                <span className="text-muted-foreground">
                    {formatDate(estimate.period_start)} –{' '}
                    {formatDate(estimate.period_end)}
                </span>
            </div>

            {estimate.otc_fee === 0 && (
                <p className="text-xs text-emerald-600 dark:text-emerald-400">
                    Biaya pasang (OTC): gratis — tidak ada biaya pasang
                </p>
            )}
        </div>
    );
}

function PaymentRow({
    label,
    value,
    strong,
    muted,
}: {
    label: string;
    value: string;
    strong?: boolean;
    muted?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-4 text-sm">
            <span className={muted ? 'text-muted-foreground' : undefined}>
                {label}
            </span>
            <span
                className={`tabular-nums ${
                    strong ? 'font-semibold' : 'font-medium'
                }`}
            >
                {value}
            </span>
        </div>
    );
}
