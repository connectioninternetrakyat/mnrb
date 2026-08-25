export type MikrotikServerRow = {
    id: number;
    name: string;
    host: string;
    port: number;
    username: string;
    has_password: boolean;
    use_tls: boolean;
    timeout: number;
    is_connected: boolean;
    last_checked_at: string | null;
    last_error: string | null;
};

export type MikrotikDetailData = {
    id: number;
    name: string;
    identity?: string | null;
    board_name?: string | null;
    architecture?: string | null;
    version?: string | null;
    uptime?: string | null;
    cpu_load?: string | null;
    free_memory?: string | null;
    total_memory?: string | null;
    routerboard_model?: string | null;
    serial_number?: string | null;
};

export type RadiusServerRow = {
    id: number;
    name: string;
    server_host: string;
    auth_port: number;
    has_shared_secret: boolean;
    timeout: number;
    db_driver: string;
    db_host: string | null;
    db_port: number | null;
    db_database: string | null;
    db_username: string | null;
    has_db_password: boolean;
    is_connected: boolean;
    last_checked_at: string | null;
    last_error: string | null;
};

export type GenieacsServerRow = {
    id: number;
    name: string;
    base_url: string;
    username: string | null;
    has_password: boolean;
    is_connected: boolean;
    last_checked_at: string | null;
    last_error: string | null;
};

export type GenieacsDeviceSummary = {
    id: string | null;
    product_class: string | null;
    manufacturer: string | null;
    serial_number: string | null;
    last_inform: string | null;
};

export type GenieacsDetailData = {
    id: number;
    name: string;
    total_devices: number;
    devices: GenieacsDeviceSummary[];
};
