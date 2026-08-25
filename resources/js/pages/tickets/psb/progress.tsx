import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { TicketInfoPanels } from '@/pages/tickets/psb/components/ticket-info';
import { show as psbShow } from '@/routes/tickets/psb';
import type { PsbTicketDetail } from '@/types';

type PageProps = {
    ticket: PsbTicketDetail;
};

export default function PsbTicketProgress({ ticket }: PageProps) {
    return (
        <>
            <Head title={`Progres ${ticket.ticket_number}`} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={`Progres ${ticket.ticket_number}`}
                        description="Ringkasan tiket yang sedang dikerjakan."
                    />

                    <Button
                        variant="outline"
                        onClick={() => router.visit(psbShow(ticket.id))}
                    >
                        <ArrowLeft />
                        Kembali ke Detail
                    </Button>
                </div>

                <TicketInfoPanels ticket={ticket} />
            </div>
        </>
    );
}

PsbTicketProgress.layout = {
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
            title: 'Progres',
            href: '',
        },
    ],
};
