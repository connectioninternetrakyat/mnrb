import {
    CalendarClock,
    Check,
    CheckCircle2,
    Copy,
    Eye,
    EyeOff,
    MapPin,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import {
    formatDateTime,
    formatPrice,
    PsbStatusBadge,
} from '@/pages/tickets/psb/components/psb-shared';
import type { PsbTicketDetail } from '@/types';

export function TicketInfoPanels({ ticket }: { ticket: PsbTicketDetail }) {
    const session = ticket.radius_session;

    return (
        <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-4 rounded-xl border p-4">
                <h3 className="text-sm font-semibold">Informasi Tiket</h3>

                <InfoRow label="Status">
                    <PsbStatusBadge status={ticket.status} />
                </InfoRow>
                <InfoRow label="Jadwal Pemasangan">
                    <span className="flex items-center gap-1.5">
                        <CalendarClock className="size-3.5 text-muted-foreground" />
                        {formatDateTime(ticket.scheduled_at)}
                    </span>
                </InfoRow>
                <InfoRow label="Teknisi">{ticket.assigned_to ?? '-'}</InfoRow>
                <InfoRow label="Dibuat">
                    {formatDateTime(ticket.created_at)}
                </InfoRow>
                <InfoRow label="Mulai Dikerjakan">
                    {formatDateTime(ticket.started_at)}
                </InfoRow>
                <InfoRow label="Selesai">
                    {formatDateTime(ticket.finished_at)}
                </InfoRow>
                {ticket.payment_method && (
                    <InfoRow label="Metode Pembayaran">
                        <Badge variant="outline">
                            {ticket.payment_method === 'tunai'
                                ? 'Tunai'
                                : 'Transfer'}
                        </Badge>
                    </InfoRow>
                )}

                {ticket.status === 'selesai' && (
                    <>
                        <InfoRow label="Akun PPPoE benar">
                            <CheckCircle2
                                className={`size-4 ${ticket.check_pppoe_account ? 'text-emerald-600' : 'text-destructive'}`}
                            />
                        </InfoRow>
                        <InfoRow label="Akun pelanggan aktif">
                            <CheckCircle2
                                className={`size-4 ${ticket.check_customer_active ? 'text-emerald-600' : 'text-destructive'}`}
                            />
                        </InfoRow>
                        <InfoRow label="VLAN 100/99 & TR-069/ACS sesuai">
                            <CheckCircle2
                                className={`size-4 ${ticket.check_vlan_tr069 ? 'text-emerald-600' : 'text-destructive'}`}
                            />
                        </InfoRow>
                    </>
                )}

                {ticket.invoice && (
                    <>
                        <h3 className="pt-2 text-sm font-semibold">Tagihan</h3>

                        <InfoRow label="No. Invoice">
                            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                {ticket.invoice.invoice_number}
                            </code>
                        </InfoRow>
                        <InfoRow label="Status Tagihan">
                            <Badge
                                variant={
                                    ticket.invoice.status === 'lunas'
                                        ? 'default'
                                        : 'secondary'
                                }
                                className={
                                    ticket.invoice.status === 'lunas'
                                        ? 'bg-emerald-600'
                                        : undefined
                                }
                            >
                                {ticket.invoice.status === 'lunas'
                                    ? 'Lunas'
                                    : 'Menunggu Pembayaran'}
                            </Badge>
                        </InfoRow>
                        <InfoRow label="Total">
                            <span className="font-semibold tabular-nums">
                                {formatPrice(
                                    ticket.invoice.payment_method === 'tunai'
                                        ? ticket.invoice.cash_total
                                        : ticket.invoice.total_exact,
                                )}
                            </span>
                        </InfoRow>
                        {ticket.invoice.va_number && (
                            <InfoRow
                                label={`No VA (${ticket.invoice.bank?.toUpperCase()})`}
                            >
                                <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs tabular-nums">
                                    {ticket.invoice.va_number
                                        .replace(/(.{4})/g, '$1 ')
                                        .trim()}
                                </code>
                            </InfoRow>
                        )}
                        {ticket.invoice.paid_at && (
                            <InfoRow label="Dibayar">
                                {formatDateTime(ticket.invoice.paid_at)}
                            </InfoRow>
                        )}
                    </>
                )}

                {ticket.finish_note && (
                    <InfoRow label="Hasil Pemasangan">
                        <p className="max-w-md text-sm whitespace-pre-line">
                            {ticket.finish_note}
                        </p>
                    </InfoRow>
                )}

                {ticket.cancel_reason && (
                    <InfoRow label="Alasan Pembatalan">
                        <p className="max-w-md text-sm whitespace-pre-line text-destructive">
                            {ticket.cancel_reason}
                        </p>
                    </InfoRow>
                )}
            </div>

            <div className="space-y-4 rounded-xl border p-4">
                <h3 className="text-sm font-semibold">Data Pelanggan</h3>

                <InfoRow label="Nama">{ticket.prospect_name}</InfoRow>
                <InfoRow label="WhatsApp">{ticket.prospect_whatsapp}</InfoRow>
                <InfoRow label="Email">{ticket.prospect_email}</InfoRow>
                <InfoRow label="Alamat">
                    <p className="max-w-md text-sm whitespace-pre-line">
                        {ticket.prospect_address}
                    </p>
                </InfoRow>
                <InfoRow label="Area">
                    {ticket.area_code ? (
                        <Badge variant="outline">{ticket.area_code}</Badge>
                    ) : (
                        '-'
                    )}
                    <span className="ml-2 text-xs text-muted-foreground">
                        {ticket.area_name}
                    </span>
                </InfoRow>
                <InfoRow label="Lokasi">
                    {ticket.latitude !== null && ticket.longitude !== null ? (
                        <a
                            href={`https://www.google.com/maps/search/${ticket.latitude},${ticket.longitude}`}
                            target="_blank"
                            rel="noreferrer"
                            className="flex items-center gap-1.5 text-primary underline-offset-2 hover:underline"
                        >
                            <MapPin className="size-3.5" />
                            {Number(ticket.latitude).toFixed(6)},{' '}
                            {Number(ticket.longitude).toFixed(6)}
                        </a>
                    ) : (
                        '-'
                    )}
                </InfoRow>
                <InfoRow label="Marketing / Pembawa">
                    {ticket.brought_by ?? '-'}
                </InfoRow>
                {ticket.note && (
                    <InfoRow label="Catatan Prospect">
                        <p className="max-w-md text-sm whitespace-pre-line">
                            {ticket.note}
                        </p>
                    </InfoRow>
                )}
            </div>

            <div className="space-y-4 rounded-xl border p-4">
                <h3 className="text-sm font-semibold">Layanan</h3>

                <InfoRow label="Paket">
                    {ticket.package_name ?? '-'}
                    {ticket.package_price !== null && (
                        <span className="ml-2 text-xs text-muted-foreground">
                            {formatPrice(ticket.package_price)}
                        </span>
                    )}
                </InfoRow>
                <InfoRow label="PPP Profile">
                    {ticket.profile_name ?? '-'}
                </InfoRow>
            </div>

            <div className="space-y-4 rounded-xl border p-4">
                <h3 className="text-sm font-semibold">Kredensial PPPoE</h3>
                <p className="-mt-2 text-xs text-muted-foreground">
                    Akun PPPoE pelanggan &amp; status sesi RADIUS
                </p>

                <CredentialRow
                    label="Username"
                    value={ticket.secret_username}
                />

                <CredentialRow
                    label="Password"
                    value={ticket.secret_password}
                    secret
                />

                <InfoRow label="Status">
                    <Badge variant="default">Aktif</Badge>
                </InfoRow>

                <h3 className="pt-2 text-sm font-semibold">Status RADIUS</h3>

                <InfoRow label="Status">
                    <Badge variant={session?.online ? 'default' : 'outline'}>
                        {session?.online ? 'Online' : 'Offline'}
                    </Badge>
                </InfoRow>
                <InfoRow label="IP">{session?.framed_ip ?? '-'}</InfoRow>
                <InfoRow label="MAC">{session?.mac ?? '-'}</InfoRow>
                <InfoRow label="NAS IP">{session?.nas_ip ?? '-'}</InfoRow>
                <InfoRow label="Uptime">
                    {session ? formatUptime(session.uptime_seconds) : '-'}
                </InfoRow>
                <InfoRow label="Mulai">
                    {session?.started_at
                        ? formatDateOnly(session.started_at)
                        : '-'}
                </InfoRow>
            </div>
        </div>
    );
}

function formatUptime(seconds: number): string {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    const parts: string[] = [];

    if (days > 0) {
        parts.push(`${days}h`);
    }

    if (hours > 0 || parts.length > 0) {
        parts.push(`${hours}j`);
    }

    parts.push(`${minutes}m`);

    return parts.join(' ');
}

function formatDateOnly(value: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

function CredentialRow({
    label,
    value,
    secret = false,
}: {
    label: string;
    value: string;
    secret?: boolean;
}) {
    const [copied, copy] = useClipboard();
    const [visible, setVisible] = useState(false);

    return (
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm">
            <span className="shrink-0 text-muted-foreground">{label}</span>

            <span className="flex items-center gap-1.5">
                <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                    {secret && !visible ? '••••••••' : value}
                </code>

                {secret && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={() => setVisible((prev) => !prev)}
                    >
                        {visible ? (
                            <EyeOff className="size-3.5" />
                        ) : (
                            <Eye className="size-3.5" />
                        )}
                        <span className="sr-only">
                            {visible
                                ? 'Sembunyikan password'
                                : 'Lihat password'}
                        </span>
                    </Button>
                )}

                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    onClick={() => copy(value)}
                >
                    {copied === value ? (
                        <Check className="size-3.5" />
                    ) : (
                        <Copy className="size-3.5" />
                    )}
                    <span className="sr-only">Salin {label}</span>
                </Button>
            </span>
        </div>
    );
}

function InfoRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
            <span className="shrink-0 text-muted-foreground">{label}</span>
            <span className="font-medium">{children}</span>
        </div>
    );
}
