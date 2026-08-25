import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    KeySquare,
    PowerOff,
    RefreshCw,
    Thermometer,
    Wifi,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { reboot as onuReboot, refresh as onuRefresh } from '@/routes/ftth/onu';
import { add as onuWanAdd, edit as onuWanEdit } from '@/routes/ftth/onu/wan';
import { add as onuWlanAdd, edit as onuWlanEdit } from '@/routes/ftth/onu/wlan';
import type { OnuHost, OnuWan, OnuWlan } from '@/types';

type OnuInfo = {
    id: number;
    genieacs_id: string;
    name: string;
    pppoe_user: string | null;
    description: string | null;
};

type Identity = {
    serial: string;
    product_class: string;
    oui: string;
    model: string;
    software: string;
    hardware: string;
    last_inform: string | null;
};

type PageProps = {
    onu: OnuInfo;
    identity: Identity;
    wans: OnuWan[];
    wlans: OnuWlan[];
    hosts: OnuHost[];
    temperatures: Array<{ path: string; value: string }>;
    wan_bases: string[];
    wlan_bases: string[];
    error: string | null;
};

export default function FtthOnuDetail({
    onu,
    identity,
    wans,
    wlans,
    hosts,
    temperatures,
    wan_bases: wanBases,
    wlan_bases: wlanBases,
    error,
}: PageProps) {
    const [wanToEdit, setWanToEdit] = useState<OnuWan | null>(null);
    const [wlanToEdit, setWlanToEdit] = useState<OnuWlan | null>(null);

    return (
        <>
            <Head title={`ONU - ${onu.name}`} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <Link
                    href="/ftth/onu"
                    className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Kembali ke daftar ONU
                </Link>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={onu.name}
                        description={
                            onu.pppoe_user
                                ? `User PPPoE: ${onu.pppoe_user}`
                                : 'Belum ada user PPPoE terkait.'
                        }
                    />

                    <div className="flex flex-wrap gap-3">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(onuRefresh(onu.id), undefined, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <RefreshCw />
                            Refresh
                        </Button>

                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.post(onuReboot(onu.id), undefined, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <PowerOff />
                            Reboot
                        </Button>
                    </div>
                </div>

                {error && (
                    <p className="rounded-xl border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        {error}
                    </p>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-xl border p-4">
                        <p className="mb-2 text-sm font-medium">Identitas</p>

                        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                            {[
                                ['Serial', identity.serial],
                                ['Product Class', identity.product_class],
                                ['OUI', identity.oui],
                                ['Model', identity.model],
                                ['Software', identity.software],
                                ['Hardware', identity.hardware],
                                ['Last Inform', identity.last_inform],
                                ['Device ID', onu.genieacs_id],
                            ].map(([label, value]) => (
                                <div
                                    key={String(label)}
                                    className="col-span-2 grid grid-cols-subgrid"
                                >
                                    <dt className="text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="font-medium break-all">
                                        {value || '-'}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>

                    <div className="rounded-xl border p-4">
                        <p className="flex items-center gap-2 text-sm font-medium">
                            <Thermometer className="size-4 shrink-0 text-muted-foreground" />
                            Monitoring Suhu Perangkat
                        </p>

                        {temperatures.length === 0 ? (
                            <p className="mt-2 text-sm text-muted-foreground">
                                Perangkat tidak melaporkan sensor suhu.
                            </p>
                        ) : (
                            <ul className="mt-2 space-y-1.5 text-sm">
                                {temperatures.map((temp) => (
                                    <li
                                        key={temp.path}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        <span className="truncate text-muted-foreground">
                                            {temp.path}
                                        </span>
                                        <Badge variant="secondary">
                                            {temp.value} °C
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                <div className="space-y-3 rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-medium">WAN Connection</p>

                        {wanBases.length > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setWanToEdit({
                                        id: '',
                                        name: '',
                                        connection_type: 'IP_Routed',
                                        status: '-',
                                        nat_enabled: null,
                                        external_ip: '-',
                                        mac: '-',
                                        vlan: '-',
                                    })
                                }
                            >
                                Tambah WAN
                            </Button>
                        )}
                    </div>

                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[560px] text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Nama
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Tipe
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        IP Publik
                                    </th>
                                    <th className="px-3 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {wans.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-3 py-6 text-center text-muted-foreground"
                                        >
                                            Belum ada WAN.
                                        </td>
                                    </tr>
                                )}
                                {wans.map((wan) => (
                                    <tr key={wan.id} className="border-t">
                                        <td className="px-3 py-2 font-medium">
                                            {wan.name}
                                        </td>
                                        <td className="px-3 py-2">
                                            {wan.connection_type}
                                        </td>
                                        <td className="px-3 py-2">
                                            {wan.status}
                                        </td>
                                        <td className="px-3 py-2 whitespace-nowrap">
                                            {wan.external_ip}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setWanToEdit(wan)
                                                }
                                            >
                                                Ubah
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="space-y-3 rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-medium">
                            WLAN (SSID &amp; Password)
                        </p>

                        {wlanBases.length > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setWlanToEdit({
                                        id: '',
                                        enable: true,
                                        ssid: '',
                                        password: '',
                                        standard: '-',
                                        channel: '-',
                                    })
                                }
                            >
                                Tambah WLAN
                            </Button>
                        )}
                    </div>

                    {wlans.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Belum ada WLAN.
                        </p>
                    )}

                    <div className="grid gap-3 md:grid-cols-2">
                        {wlans.map((wlan) => (
                            <div
                                key={wlan.id}
                                className="rounded-lg border p-3 text-sm"
                            >
                                <div className="flex items-center gap-2">
                                    <Wifi className="size-4 shrink-0 text-muted-foreground" />
                                    <span className="font-medium">
                                        SSID: {wlan.ssid}
                                    </span>
                                    <Badge
                                        variant={
                                            wlan.enable ? 'default' : 'outline'
                                        }
                                        className="ml-auto"
                                    >
                                        {wlan.enable ? 'aktif' : 'mati'}
                                    </Badge>
                                </div>

                                <dl className="mt-2 space-y-1 text-muted-foreground">
                                    <div className="flex items-center justify-between gap-2">
                                        <dt className="flex items-center gap-1">
                                            <KeySquare className="size-3.5" />{' '}
                                            Password
                                        </dt>
                                        <dd>
                                            {wlan.password || '(tidak terbaca)'}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-2">
                                        <dt>Standard / Channel</dt>
                                        <dd>
                                            {wlan.standard} — ch {wlan.channel}
                                        </dd>
                                    </div>
                                </dl>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-2 w-full"
                                    onClick={() => setWlanToEdit(wlan)}
                                >
                                    Ubah SSID / Password
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="space-y-2 rounded-xl border p-4">
                    <p className="text-sm font-medium">Perangkat Terhubung</p>

                    {hosts.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada host terdeteksi.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[480px] text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Hostname
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            IP
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            MAC
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {hosts.map((host, index) => (
                                        <tr
                                            key={`${host.mac}-${index}`}
                                            className="border-t"
                                        >
                                            <td className="px-3 py-2">
                                                {host.hostname}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {host.ip}
                                            </td>
                                            <td className="px-3 py-2 break-all">
                                                {host.mac}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge
                                                    variant={
                                                        host.active
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {host.active
                                                        ? 'online'
                                                        : 'offline'}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {wanToEdit && (
                <WanDialog
                    key={`wan-${wanToEdit.id || 'new'}`}
                    onu={onu}
                    wan={wanToEdit.id ? wanToEdit : null}
                    bases={wanBases}
                    onClose={() => setWanToEdit(null)}
                />
            )}

            {wlanToEdit && (
                <WlanDialog
                    key={`wlan-${wlanToEdit.id || 'new'}`}
                    onu={onu}
                    wlan={wlanToEdit.id ? wlanToEdit : null}
                    bases={wlanBases}
                    onClose={() => setWlanToEdit(null)}
                />
            )}
        </>
    );
}

function WanDialog({
    onu,
    wan,
    bases,
    onClose,
}: {
    onu: OnuInfo;
    wan: OnuWan | null;
    bases: string[];
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {wan ? `Ubah WAN - ${wan.name}` : 'Tambah WAN'}
                    </DialogTitle>
                    <DialogDescription>
                        Perintah TR-069 dikirim langsung ke perangkat.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(wan
                        ? onuWanEdit.form(onu.id)
                        : onuWanAdd.form(onu.id))}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="path"
                                value={wan?.id ?? bases[0] ?? ''}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="wan-name">Nama Koneksi</Label>
                                <Input
                                    id="wan-name"
                                    name="name"
                                    required
                                    autoFocus
                                    defaultValue={
                                        wan && wan.name !== '-' ? wan.name : ''
                                    }
                                    placeholder="pppoe-bridge"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="wan-type">
                                    Connection Type
                                </Label>
                                <Select
                                    name="connection_type"
                                    defaultValue={
                                        wan && wan.connection_type !== '-'
                                            ? wan.connection_type
                                            : 'IP_Routed'
                                    }
                                >
                                    <SelectTrigger
                                        id="wan-type"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent
                                        position="popper"
                                        avoidCollisions
                                        align="start"
                                        className="max-h-none"
                                    >
                                        <SelectItem value="IP_Routed">
                                            IP Routed
                                        </SelectItem>
                                        <SelectItem value="IP_Bridged">
                                            IP Bridged
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.connection_type} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="wan-vlan">VLAN ID</Label>
                                    <Input
                                        id="wan-vlan"
                                        name="vlan_id"
                                        type="number"
                                        min={1}
                                        max={4094}
                                        defaultValue={
                                            wan && wan.vlan !== '-'
                                                ? Number(wan.vlan)
                                                : undefined
                                        }
                                    />
                                    <InputError message={errors.vlan_id} />
                                </div>

                                <label className="mt-6 flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="nat_enabled"
                                        value="1"
                                        defaultChecked={
                                            wan?.nat_enabled ?? true
                                        }
                                    />
                                    Aktifkan NAT
                                </label>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="wan-user">Username</Label>
                                    <Input
                                        id="wan-user"
                                        name="username"
                                        autoComplete="off"
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="wan-pass">Password</Label>
                                    <Input
                                        id="wan-pass"
                                        name="password"
                                        type="password"
                                        autoComplete="off"
                                    />
                                </div>
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Batal
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    Kirim ke Perangkat
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function WlanDialog({
    onu,
    wlan,
    bases,
    onClose,
}: {
    onu: OnuInfo;
    wlan: OnuWlan | null;
    bases: string[];
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {wlan ? `Ubah WLAN - ${wlan.ssid}` : 'Tambah WLAN'}
                    </DialogTitle>
                    <DialogDescription>
                        SSID & password baru dikirim via TR-069.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(wlan
                        ? onuWlanEdit.form(onu.id)
                        : onuWlanAdd.form(onu.id))}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="path"
                                value={wlan?.id ?? bases[0] ?? ''}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="wlan-ssid">SSID</Label>
                                <Input
                                    id="wlan-ssid"
                                    name="ssid"
                                    required
                                    autoFocus
                                    maxLength={32}
                                    defaultValue={
                                        wlan && wlan.ssid !== '-'
                                            ? wlan.ssid
                                            : ''
                                    }
                                    placeholder="WiFi-Pelanggan"
                                />
                                <InputError message={errors.ssid} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="wlan-pass">Password WiFi</Label>
                                <Input
                                    id="wlan-pass"
                                    name="password"
                                    minLength={8}
                                    maxLength={63}
                                    placeholder={
                                        wlan
                                            ? 'Kosongkan jika tidak diubah'
                                            : 'Min. 8 karakter'
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    WPA2-PSK diterapkan otomatis.
                                </p>
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="wlan-channel">
                                    Channel (0 = auto)
                                </Label>
                                <Input
                                    id="wlan-channel"
                                    name="channel"
                                    type="number"
                                    min={0}
                                    max={165}
                                    defaultValue={
                                        wlan && wlan.channel !== '-'
                                            ? Number(wlan.channel)
                                            : undefined
                                    }
                                />
                                <InputError message={errors.channel} />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Batal
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    Kirim ke Perangkat
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

FtthOnuDetail.layout = {
    breadcrumbs: [
        { title: 'FTTH', href: '/ftth/onu' },
        { title: 'ONU/ONT', href: '/ftth/onu' },
        { title: 'Detail', href: '#' },
    ],
};
