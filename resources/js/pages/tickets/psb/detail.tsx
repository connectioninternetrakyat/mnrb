import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Ban, CheckCircle2, PlayCircle } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Textarea } from '@/components/ui/textarea';
import { TicketInfoPanels } from '@/pages/tickets/psb/components/ticket-info';
import {
    cancel as psbCancel,
    finish as psbFinishPage,
    index as psbIndex,
} from '@/routes/tickets/psb';
import { store as psbProgressStore } from '@/routes/tickets/psb/progress';
import type { PsbTicketDetail } from '@/types';

type PageProps = {
    ticket: PsbTicketDetail;
    auth: {
        user: {
            permissions: string[];
        };
    };
};

export default function PsbTicketDetail({ ticket }: PageProps) {
    const { auth } = usePage<PageProps>().props;

    const canUpdate = auth.user.permissions.includes('tickets.update');

    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelReason, setCancelReason] = useState('');

    const submitCancel = () => {
        if (cancelReason.trim().length < 5) {
            return;
        }

        router.post(
            psbCancel(ticket.id),
            { cancel_reason: cancelReason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCancelOpen(false);
                    setCancelReason('');
                },
            },
        );
    };

    return (
        <>
            <Head title={`Tiket ${ticket.ticket_number}`} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={ticket.ticket_number}
                        description="Detail tiket pasang baru."
                    />

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.visit(psbIndex())}
                        >
                            <ArrowLeft />
                            Daftar Tiket
                        </Button>

                        {canUpdate && ticket.status === 'terjadwal' && (
                            <Button
                                onClick={() =>
                                    router.post(
                                        psbProgressStore(ticket.id),
                                        undefined,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <PlayCircle />
                                Progres
                            </Button>
                        )}

                        {canUpdate && ticket.status === 'dikerjakan' && (
                            <>
                                <Button
                                    onClick={() =>
                                        router.visit(psbFinishPage(ticket.id))
                                    }
                                >
                                    <CheckCircle2 />
                                    Selesaikan
                                </Button>

                                <Button
                                    variant="destructive"
                                    onClick={() => setCancelOpen(true)}
                                >
                                    <Ban />
                                    Batalkan
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <TicketInfoPanels ticket={ticket} />
            </div>

            <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Batalkan tiket?</DialogTitle>
                        <DialogDescription>
                            Tiket {ticket.ticket_number} akan dibatalkan. Alasan
                            pembatalan wajib diisi.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Textarea
                            rows={3}
                            value={cancelReason}
                            onChange={(e) => setCancelReason(e.target.value)}
                            placeholder="Contoh: pelanggan membatalkan, alamat tidak ditemukan, jadwal bertabrakan..."
                        />

                        <InputError
                            message={
                                cancelReason.length > 0 &&
                                cancelReason.trim().length < 5
                                    ? 'Alasan minimal 5 karakter.'
                                    : undefined
                            }
                        />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Tutup</Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            disabled={cancelReason.trim().length < 5}
                            onClick={submitCancel}
                        >
                            <Ban />
                            Batalkan Tiket
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PsbTicketDetail.layout = {
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
            title: 'Detail',
            href: '',
        },
    ],
};
