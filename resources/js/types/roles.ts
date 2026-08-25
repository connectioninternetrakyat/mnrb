export type RoleRow = {
    id: number;
    name: string;
    description: string | null;
    users_count: number;
    locked: boolean;
    menus: string[];
};

export type RoleDetail = {
    id: number;
    name: string;
};

export type MenuOption = {
    value: string;
    label: string;
};
