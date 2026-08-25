export type UserRow = {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;
    whatsapp: string | null;
    email_verified_at: string | null;
    created_at: string;
    roles: string[];
};

export type RoleOption = {
    value: string;
    label: string;
};

export type UserFilters = {
    search?: string | null;
    role?: string | null;
};
