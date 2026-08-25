import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { qr as wahaQrRoute } from '@/routes/gateways/waha';
import type { WahaServerRow } from '@/types';

export function WahaQrDialog({
    server,
    onClose,
}: {
    server: WahaServerRow;
    onClose: () => void;
}) {
    const [qr, setQr] = useState<string | null>(null);
    const [status, setStatus] = useState<string>('memuat...');
    const [error, setError] = useState<string | null>(null);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const onCloseRef = useRef(onClose);

    useEffect(() => {
        onCloseRef.current = onClose;

        let cancelled = false;

        // Poll sequentially: schedule the next attempt only after the
        // previous request finished, so slow responses never pile up.
        const schedule = () => {
            if (!cancelled) {
                timer.current = setTimeout(load, 3000);
            }
        };

        const load = async () => {
            try {
                const response = await fetch(wahaQrRoute(server.id).url, {
                    headers: { Accept: 'application/json' },
                });
                const data = (await response.json()) as {
                    qr?: string | null;
                    status?: string;
                    error?: string;
                };

                if (cancelled) {
                    return;
                }

                if (data.qr) {
                    setQr(data.qr);
                }

                if (data.status) {
                    setStatus(data.status);

                    // Connected: stop polling and close after a beat.
                    if (data.status === 'WORKING') {
                        if (timer.current) {
                            clearTimeout(timer.current);
                        }

                        router.reload({ only: ['servers'] });

                        setTimeout(() => onCloseRef.current(), 1200);

                        return;
                    }
                }

                if (!data.qr && data.error) {
                    setError(data.error);
                }
            } catch {
                if (!cancelled) {
                    setError('Gagal mengambil QR dari server WAHA.');
                }
            }

            schedule();
        };

        load();

        return () => {
            cancelled = true;

            if (timer.current) {
                clearTimeout(timer.current);
            }
        };
    }, [server.id]);

    const connected = status === 'WORKING';

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Scan QR — {server.name}</DialogTitle>
                    <DialogDescription>
                        Buka WhatsApp di ponsel:{' '}
                        <span className="font-medium">
                            Setelan &gt; Perangkat Tertaut &gt; Tautkan
                            Perangkat
                        </span>
                        , lalu arahkan ke kode berikut.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col items-center gap-3 py-2">
                    {connected ? (
                        <>
                            <Badge className="bg-emerald-600">
                                Terhubung (WORKING)
                            </Badge>
                            <p className="text-sm text-muted-foreground">
                                Session WhatsApp berhasil ditautkan.
                            </p>
                        </>
                    ) : (
                        <>
                            {qr ? (
                                <img
                                    src={qr}
                                    alt="QR WhatsApp"
                                    className="size-64 rounded-lg border bg-white p-2"
                                />
                            ) : (
                                <div className="flex size-64 items-center justify-center rounded-lg border border-dashed text-xs text-muted-foreground">
                                    {error ?? 'Memuat QR...'}
                                </div>
                            )}

                            <Badge variant="secondary">
                                Status: {status || 'memuat'}
                            </Badge>

                            {error && (
                                <p className="text-center text-xs text-destructive">
                                    {error}
                                </p>
                            )}
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
