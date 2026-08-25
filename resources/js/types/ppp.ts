export type PppProfileRow = {
    id: number;
    name: string;
    local_address: string | null;
    remote_address: string | null;
    dns_server: string | null;
    rate_limit: string | null;
    radius_synced: boolean;
};

export type PppSecretRow = {
    id: number;
    name: string;
    profile: string;
    disabled_on_mikrotik: boolean;
    radius_enabled: boolean;
    radius_profile: string | null;
};

export type PppActiveSession = {
    id: string;
    name: string;
    service: string;
    caller_id: string;
    address: string;
    uptime: string;
    encoding: string;
};

export type PppServerBadge = {
    id: number;
    name: string;
} | null;

export type PppPaginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};

export type PppFilters = {
    search?: string | null;
};
