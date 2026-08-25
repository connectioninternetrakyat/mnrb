export type BillingSettings = {
    prorate_cut_day: number;
    invoice_day: number;
    isolir_day: number | null;
    isolir_hour: string;
    isolir_profile: string;
    ppn_percent: number;
    promo_percent: number;
    remind_h3: boolean;
    remind_h2: boolean;
    remind_h1: boolean;
};

export type BillRow = {
    id: number;
    bill_number: string;
    period: string;
    total: number;
    dpp: number;
    ppn: number;
    status: 'menunggu_pembayaran' | 'lunas';
    paid_at: string | null;
    customer_name: string | null;
    customer_whatsapp: string | null;
    area_code: string | null;
    package_name: string | null;
    ticket_id: number | null;
    ticket_number: string | null;
};

export type CustomerRow = {
    id: number;
    name: string;
    email: string | null;
    whatsapp: string;
    address: string;
    area_code_id: number;
    area_code: string | null;
    area_name: string | null;
    package_id: number;
    package_name: string | null;
    package_price: number | null;
    status: 'aktif' | 'ditangguhkan';
    notes: string | null;
    secret_username: string | null;
    secret_profile: string | null;
    has_secret: boolean;
};

export type InvoiceRow = {
    id: number;
    invoice_number: string;
    status: 'menunggu_pembayaran' | 'lunas';
    payment_method: 'tunai' | 'transfer';
    bank: string | null;
    va_number: string | null;
    total_exact: number;
    cash_total: number;
    paid_at: string | null;
    created_at: string | null;
    ticket_id: number | null;
    ticket_number: string | null;
    customer_name: string | null;
    customer_whatsapp: string | null;
    package_name: string | null;
};
