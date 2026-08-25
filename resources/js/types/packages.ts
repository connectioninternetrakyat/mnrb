export type PackageRow = {
    id: number;
    name: string;
    ppp_profile_id: number;
    price: number;
    description: string | null;
    profile_name: string | null;
    rate_limit: string | null;
    remote_address: string | null;
};

export type PackageProfileOption = {
    id: number;
    name: string;
    rate_limit: string | null;
    remote_address: string | null;
    radius_synced: boolean;
    has_package: boolean;
};

export type PackagePaginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};
