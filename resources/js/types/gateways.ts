export type MidtransAccountRow = {
    id: number;
    name: string;
    is_active: boolean;
    is_production: boolean;
    merchant_id: string | null;
    client_key: string | null;
    has_server_key: boolean;
    last_notified_at: string | null;
};

export type WahaServerRow = {
    id: number;
    name: string;
    base_url: string;
    session: string;
    has_api_key: boolean;
    is_connected: boolean;
    last_webhook_at: string | null;
    last_checked_at: string | null;
    last_error: string | null;
};
