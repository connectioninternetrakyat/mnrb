export type AreaCodeRow = {
    id: number;
    code: string;
    name: string;
    region: string | null;
    description: string | null;
    devices_count?: number;
};

export type AreaCodeOption = {
    id: number;
    code: string;
    name: string;
};

export type OdpOdcRow = {
    id: number;
    code: string;
    name: string;
    area_code_id: number;
    area_code: string | null;
    area_name: string | null;
    region: string | null;
    port: number | null;
    splitter_ratio: string | null;
    maps_url: string | null;
    latitude: number | null;
    longitude: number | null;
    description: string | null;
};

export type OnuDeviceRow = {
    device_id: string;
    genieacs_id?: string;
    description?: string | null;
    serial: string;
    product_class: string;
    oui: string;
    last_inform: string | null;
    onu_id: number | null;
    customer_name: string | null;
    pppoe_user: string | null;
};

export type OnuOption = {
    value: string;
    label: string;
};

export type OnuWan = {
    id: string;
    name: string;
    connection_type: string;
    status: string;
    nat_enabled: boolean | null;
    external_ip: string;
    mac: string;
    vlan: string;
};

export type OnuWlan = {
    id: string;
    enable: boolean;
    ssid: string;
    password: string;
    standard: string;
    channel: string;
};

export type OnuHost = {
    hostname: string;
    ip: string;
    mac: string;
    interface: string;
    active: boolean;
};
