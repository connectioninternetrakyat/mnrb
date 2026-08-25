export type ProspectStatus = 'menunggu_verifikasi' | 'disetujui' | 'ditolak';

export type ProspectRow = {
    id: number;
    name: string;
    email: string;
    whatsapp: string;
    address: string;
    area_code_id: number;
    area_code: string | null;
    area_name: string | null;
    package_id: number;
    package_name: string | null;
    package_price: number | null;
    profile_name: string | null;
    rate_limit: string | null;
    brought_by: string | null;
    maps_url: string | null;
    latitude: number | null;
    longitude: number | null;
    status: ProspectStatus;
    approved_at: string | null;
    note: string | null;
};

export type ProspectPackageOption = {
    id: number;
    name: string;
    price: number;
    profile_name: string | null;
    rate_limit: string | null;
};

export type ProspectFilters = {
    search?: string | null;
    status?: string | null;
};
