export type PsbTicketStatus =
    'terjadwal' | 'dikerjakan' | 'selesai' | 'dibatalkan';

export type PsbTicketRow = {
    id: number;
    ticket_number: string;
    scheduled_at: string;
    status: PsbTicketStatus;
    assigned_to: string | null;
    prospect_name: string | null;
    prospect_whatsapp: string | null;
    prospect_address: string | null;
    area_code: string | null;
    package_name: string | null;
};

export type PsbTicketDetail = PsbTicketRow & {
    created_at: string | null;
    started_at: string | null;
    finished_at: string | null;
    cancelled_at: string | null;
    progress_note: string | null;
    finish_note: string | null;
    cancel_reason: string | null;
    payment_method: 'tunai' | 'transfer' | null;
    check_pppoe_account: boolean;
    check_customer_active: boolean;
    check_vlan_tr069: boolean;
    invoice: TicketInvoice | null;
    prospect_id: number;
    prospect_email: string;
    area_name: string | null;
    package_price: number | null;
    profile_name: string | null;
    brought_by: string | null;
    maps_url: string | null;
    latitude: number | null;
    longitude: number | null;
    note: string | null;
    secret_username: string;
    secret_password: string;
    secret_profile: string | null;
    radius_session: RadiusSession | null;
};

export type TicketInvoice = {
    invoice_number: string;
    status: 'menunggu_pembayaran' | 'lunas';
    payment_method: 'tunai' | 'transfer';
    bank: string | null;
    va_number: string | null;
    total_exact: number;
    cash_total: number;
    paid_at: string | null;
};

export type RadiusSession = {
    online: boolean;
    framed_ip: string | null;
    mac: string | null;
    nas_ip: string | null;
    uptime_seconds: number;
    started_at: string | null;
};

export type InvoiceEstimate = {
    monthly_price: number;
    period_days: number;
    active_days: number;
    prorata: number;
    otc_fee: number;
    promo_percent: number;
    discount: number;
    dpp: number;
    ppn: number;
    total_exact: number;
    cash_total: number;
    cash_rounding: number;
    period_start: string;
    period_end: string;
};
