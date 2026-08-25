export type PelangganStatus = 'aktif' | 'berhenti';

export type PelangganRow = {
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
    status: PelangganStatus;
    joined_at: string | null;
    notes: string | null;
    secret_username: string | null;
    secret_profile: string | null;
    secret_disabled: boolean;
};

export type PelangganSecretOption = {
    id: number;
    name: string;
    profile: string | null;
};
