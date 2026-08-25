import { Badge } from '@/components/ui/badge';
import type { PsbTicketStatus } from '@/types';

export const psbStatusLabels: Record<PsbTicketStatus, string> = {
    terjadwal: 'Terjadwal',
    dikerjakan: 'Dikerjakan',
    selesai: 'Selesai',
    dibatalkan: 'Dibatalkan',
};

export const psbStatusBadgeVariant: Record<
    PsbTicketStatus,
    'secondary' | 'default' | 'outline' | 'destructive'
> = {
    terjadwal: 'secondary',
    dikerjakan: 'default',
    selesai: 'outline',
    dibatalkan: 'destructive',
};

export function PsbStatusBadge({ status }: { status: PsbTicketStatus }) {
    return (
        <Badge variant={psbStatusBadgeVariant[status]}>
            {psbStatusLabels[status]}
        </Badge>
    );
}

export const formatDateTime = (value: string | null) =>
    value === null
        ? '-'
        : new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value));

export const formatPrice = (price: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(price);
