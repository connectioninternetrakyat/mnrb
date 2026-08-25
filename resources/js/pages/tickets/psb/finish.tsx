import { Form, Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    Check,
    CheckCircle2,
    Copy,
    Landmark,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useClipboard } from '@/hooks/use-clipboard';
import { PaymentEstimateCard } from '@/pages/tickets/psb/components/payment-estimate-card';
import {
    formatDateTime,
    PsbStatusBadge,
} from '@/pages/tickets/psb/components/psb-shared';
import { TicketInfoPanels } from '@/pages/tickets/psb/components/ticket-info';
import { show as psbShow } from '@/routes/tickets/psb';
import { store as psbFinishStore } from '@/routes/tickets/psb/finish';
import type { InvoiceEstimate, PsbTicketDetail } from '@/types';

type PageProps = {
    ticket: PsbTicketDetail;
    estimate: InvoiceEstimate;
};

const banks = [
    { code: 'bri', name: 'BRI', prefix: '888' },
    { code: 'bni', name: 'BNI', prefix: '988' },
    { code: 'bca', name: 'BCA', prefix: '8077' },
    { code: 'mandiri', name: 'Mandiri', prefix: '895' },
] as const;

type Bank = (typeof banks)[number];

/**
 * Nomor WhatsApp diubah ke format lokal berawalan 08,
 * lalu dipad/dipotong agar total No VA selalu 16 digit.
 */
const whatsappSegment = (whatsapp: string | null, length: number): string => {
    const digits = (whatsapp ?? '').replace(/\D/g, '');

    let local: string;

    if (digits.startsWith('62')) {
        local = `0${digits.slice(2)}`;
    } else if (digits.startsWith('0')) {
        local = digits;
    } else {
        local = `0${digits}`;
    }

    return local.padEnd(length, '0').slice(0, length);
};

const formatVa = (prefix: string, whatsapp: string | null) =>
    `${prefix}${whatsappSegment(whatsapp, 16 - prefix.length)}`;

/** Tampilan dikelompokkan per 4 digit agar mudah dibaca. */
const groupVa = (va: string) => va.replace(/(.{4})/g, '$1 ').trim();

export default function PsbTicketFinish({ ticket, estimate }: PageProps) {
    const [paymentMethod, setPaymentMethod] = useState<
        'tunai' | 'transfer' | ''
    >('');
    const [selectedBank, setSelectedBank] = useState<Bank | null>(null);
    const [copied, copy] = useClipboard();
    const [checks, setChecks] = useState({ pppoe: false, aktif: false, vlan: false });

    return (
        <>
            <Head title={`Selesaikan ${ticket.ticket_number}`} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={`Selesaikan ${ticket.ticket_number}`}
                        description="Tutup tiket dengan hasil pemasangan."
                    />

                    <Button
                        variant="outline"
                        onClick={() => router.visit(psbShow(ticket.id))}
                    >
                        <ArrowLeft />
                        Kembali ke Detail
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-4 rounded-xl border p-4 text-sm">
                    <PsbStatusBadge status={ticket.status} />
                    <span className="text-muted-foreground">
                        Pelanggan: {ticket.prospect_name}
                    </span>
                    <span className="text-muted-foreground">
                        Secret:{' '}
                        <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                            {ticket.secret_username ?? '-'}
                        </code>
                    </span>
                    <span className="text-muted-foreground">
                        Mulai: {formatDateTime(ticket.started_at)}
                    </span>
                </div>

                <TicketInfoPanels ticket={ticket} />

                <div className="grid gap-4 lg:grid-cols-2">
                    <PaymentEstimateCard estimate={estimate} />

                    <div className="space-y-3 rounded-xl border p-4">
                        <h3 className="text-sm font-semibold">
                            Metode Pembayaran
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            Pilih cara pembayaran tagihan aktivasi oleh
                            pelanggan.
                        </p>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <PaymentMethodOption
                                label="Tunai"
                                description="Dibayar langsung, dibulatkan ke Rp1.000"
                                icon={Banknote}
                                selected={paymentMethod === 'tunai'}
                                onSelect={() => {
                                    setPaymentMethod('tunai');
                                    setSelectedBank(null);
                                }}
                            />

                            <PaymentMethodOption
                                label="Transfer"
                                description="VA / transfer bank, ditagih exact"
                                icon={Landmark}
                                selected={paymentMethod === 'transfer'}
                                onSelect={() => setPaymentMethod('transfer')}
                            />
                        </div>

                        {paymentMethod === 'transfer' && (
                            <>
                                <div className="space-y-2 border-t pt-3">
                                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Bank
                                    </p>

                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        {banks.map((bank) => (
                                            <button
                                                key={bank.code}
                                                type="button"
                                                onClick={() =>
                                                    setSelectedBank(bank)
                                                }
                                                className={`rounded-lg border px-2 py-2 text-sm font-medium transition-colors ${
                                                    selectedBank?.code ===
                                                    bank.code
                                                        ? 'border-primary bg-primary/5 text-primary'
                                                        : 'hover:bg-muted/50'
                                                }`}
                                            >
                                                {bank.name}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {selectedBank && (
                                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t pt-3">
                                        <span className="text-sm text-muted-foreground">
                                            No VA ({selectedBank.name})
                                        </span>

                                        <span className="flex items-center gap-2">
                                            <code className="rounded-lg bg-muted px-3 py-1.5 font-mono text-lg font-bold tracking-wider tabular-nums sm:text-xl">
                                                {groupVa(
                                                    formatVa(
                                                        selectedBank.prefix,
                                                        ticket.prospect_whatsapp,
                                                    ),
                                                )}
                                            </code>

                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                onClick={() =>
                                                    copy(
                                                        formatVa(
                                                            selectedBank.prefix,
                                                            ticket.prospect_whatsapp,
                                                        ),
                                                    )
                                                }
                                            >
                                                {copied ===
                                                formatVa(
                                                    selectedBank.prefix,
                                                    ticket.prospect_whatsapp,
                                                ) ? (
                                                    <Check className="size-4" />
                                                ) : (
                                                    <Copy className="size-4" />
                                                )}
                                                <span className="sr-only">
                                                    Salin No VA
                                                </span>
                                            </Button>
                                        </span>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>

                <Form
                    {...psbFinishStore.form(ticket.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-none space-y-4 rounded-xl border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="payment_method"
                                value={paymentMethod}
                            />
                            <input
                                type="hidden"
                                name="bank"
                                value={selectedBank?.code ?? ''}
                            />
                            <input
                                type="hidden"
                                name="check_pppoe_account"
                                value={checks.pppoe ? '1' : '0'}
                            />
                            <input
                                type="hidden"
                                name="check_customer_active"
                                value={checks.aktif ? '1' : '0'}
                            />
                            <input
                                type="hidden"
                                name="check_vlan_tr069"
                                value={checks.vlan ? '1' : '0'}
                            />

                            <div className="space-y-1">
                                <h3 className="text-sm font-semibold">
                                    Hasil Pemasangan
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Centang seluruh verifikasi untuk menutup
                                    tiket. Invoice aktivasi otomatis terbit
                                    setelah tiket ditutup.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                {(
                                    [
                                        {
                                            key: 'pppoe',
                                            name: 'check_pppoe_account',
                                            label: 'Akun PPPoE sudah benar',
                                            error: errors.check_pppoe_account,
                                        },
                                        {
                                            key: 'aktif',
                                            name: 'check_customer_active',
                                            label: 'Akun pelanggan sudah aktif',
                                            error: errors.check_customer_active,
                                        },
                                        {
                                            key: 'vlan',
                                            name: 'check_vlan_tr069',
                                            label: 'VLAN 100/99 & TR-069/ACS sudah sesuai',
                                            error: errors.check_vlan_tr069,
                                        },
                                    ] as const
                                ).map((item) => (
                                    <label
                                        key={item.key}
                                        className={`flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors ${
                                            checks[item.key]
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:bg-muted/50'
                                        }`}
                                    >
                                        <Checkbox
                                            checked={checks[item.key]}
                                            onCheckedChange={(checked) =>
                                                setChecks((prev) => ({
                                                    ...prev,
                                                    [item.key]:
                                                        checked === true,
                                                }))
                                            }
                                        />

                                        <span className="text-sm font-medium">
                                            {item.label}
                                        </span>
                                    </label>
                                ))}

                                <InputError
                                    message={
                                        errors.check_pppoe_account ??
                                        errors.check_customer_active ??
                                        errors.check_vlan_tr069
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="finish-note">
                                    Catatan{' '}
                                    <span className="text-muted-foreground">
                                        (opsional)
                                    </span>
                                </Label>

                                <Textarea
                                    id="finish-note"
                                    name="finish_note"
                                    rows={4}
                                    placeholder="Contoh: ODP-TCL01 port 3, redaman 24.5 dB..."
                                />

                                <InputError
                                    message={
                                        errors.finish_note ?? errors.payment_method
                                    }
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.visit(psbShow(ticket.id))
                                    }
                                >
                                    Batal
                                </Button>

                                <Button
                                    disabled={
                                        processing ||
                                        !paymentMethod ||
                                        !checks.pppoe ||
                                        !checks.aktif ||
                                        !checks.vlan
                                    }
                                >
                                    <CheckCircle2 />
                                    Tutup / Selesaikan Tiket
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

function PaymentMethodOption({
    label,
    description,
    icon: Icon,
    selected,
    onSelect,
}: {
    label: string;
    description: string;
    icon: typeof Banknote;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            className={`flex items-start gap-3 rounded-xl border p-3 text-left transition-colors ${
                selected ? 'border-primary bg-primary/5' : 'hover:bg-muted/50'
            }`}
        >
            <span
                className={`mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg ${
                    selected ? 'bg-primary text-primary-foreground' : 'bg-muted'
                }`}
            >
                <Icon className="size-4" />
            </span>

            <span className="space-y-0.5">
                <span className="block text-sm font-medium">{label}</span>
                <span className="block text-xs text-muted-foreground">
                    {description}
                </span>
            </span>
        </button>
    );
}

PsbTicketFinish.layout = {
    breadcrumbs: [
        {
            title: 'Tikets',
            href: '/tickets/psb',
        },
        {
            title: 'PSB',
            href: '/tickets/psb',
        },
        {
            title: 'Selesaikan',
            href: '',
        },
    ],
};
