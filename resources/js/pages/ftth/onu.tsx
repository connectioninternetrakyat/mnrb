import { Form, Head, Link, router } from '@inertiajs/react';
import {
    MapPinned,
    MonitorSmartphone,
    MoreHorizontal,
    Search,
    Trash2,
    UserRoundPen,
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    destroy as onuDestroy,
    index as ftthOnuIndex,
    show as ftthOnuShow,
    store as onuStore,
    update as onuUpdate,
} from '@/routes/ftth/onu';
import type { OnuDeviceRow, OnuOption } from '@/types';

type PageProps = {
    devices: {
        data: OnuDeviceRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    unregistered: OnuOption[];
    pelanggans: {
        id: number;
        name: string;
        whatsapp: string;
        secret_username: string | null;
    }[];
    filters: {
        search?: string | null;
    };
    genieacs_server: {
        id: number;
        name: string;
    } | null;
};

export default function FtthOnu({
    devices,
    unregistered,
    pelanggans,
    filters,
    genieacs_server: genieacsServer,
}: PageProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [deviceToRegister, setDeviceToRegister] =
        useState<OnuDeviceRow | null>(null);
    const [onuToEdit, setOnuToEdit] = useState<OnuDeviceRow | null>(null);
    const [onuToDelete, setOnuToDelete] = useState<OnuDeviceRow | null>(null);

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            ftthOnuIndex(),
            { search },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="ONU/ONT" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="ONU/ONT"
                        description="Perangkat pelanggan terhubung GenieACS — WAN, WLAN, monitoring & reboot."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Daftarkan ONU
                    </Button>
                </div>

                {genieacsServer && (
                    <div className="flex items-center gap-2 rounded-xl border p-4 text-sm">
                        <MonitorSmartphone className="size-4 shrink-0 text-muted-foreground" />
                        GenieACS:
                        <Badge variant="secondary">{genieacsServer.name}</Badge>
                    </div>
                )}

                <form
                    onSubmit={submitSearch}
                    className="relative w-full sm:w-80"
                >
                    <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />

                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="pl-8"
                        placeholder="Cari nama pelanggan / user pppoe / serial..."
                    />
                </form>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Pelanggan
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    User PPPoE
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Serial
                                </th>
                                <th className="px-4 py-3 font-medium">Tipe</th>
                                <th className="px-4 py-3 font-medium">
                                    Last Inform
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {devices.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Tidak ada perangkat.
                                    </td>
                                </tr>
                            )}

                            {devices.data.map((device) => (
                                <tr key={device.device_id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            {device.onu_id ? (
                                                <Link
                                                    href={ftthOnuShow({
                                                        onu: device.onu_id,
                                                    })}
                                                    prefetch
                                                    className="font-medium hover:underline"
                                                >
                                                    {device.customer_name}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground italic">
                                                    belum terdaftar
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {device.pppoe_user || '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {device.serial}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {device.product_class}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                        {device.last_inform ?? '-'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                >
                                                    <MoreHorizontal />
                                                    <span className="sr-only">
                                                        Aksi
                                                    </span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuLabel>
                                                    Aksi
                                                </DropdownMenuLabel>
                                                <DropdownMenuSeparator />

                                                {device.onu_id ? (
                                                    <>
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={ftthOnuShow(
                                                                    {
                                                                        onu: device.onu_id,
                                                                    },
                                                                )}
                                                            >
                                                                <MonitorSmartphone />
                                                                Detail & Kelola
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                setOnuToEdit(
                                                                    device,
                                                                )
                                                            }
                                                        >
                                                            <UserRoundPen />
                                                            Ubah Data
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                setOnuToDelete(
                                                                    device,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 />
                                                            Lepas Pemetaan
                                                        </DropdownMenuItem>
                                                    </>
                                                ) : (
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setDeviceToRegister(
                                                                device,
                                                            )
                                                        }
                                                    >
                                                        <MapPinned />
                                                        Daftarkan
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {devices.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Halaman {devices.current_page} dari{' '}
                            {devices.last_page} ({devices.total} perangkat)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!devices.links[0]?.url}
                                onClick={() =>
                                    devices.links[0]?.url &&
                                    router.visit(devices.links[0].url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Sebelumnya
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    !devices.links[devices.links.length - 1]
                                        ?.url
                                }
                                onClick={() =>
                                    devices.links[devices.links.length - 1]
                                        ?.url &&
                                    router.visit(
                                        devices.links[devices.links.length - 1]
                                            .url as string,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <OnuDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
                unregistered={unregistered}
                pelanggans={pelanggans}
            />

            {deviceToRegister && !onuToEdit && (
                <OnuDialog
                    key={`register-${deviceToRegister.device_id}`}
                    open
                    onOpenChange={(open) => !open && setDeviceToRegister(null)}
                    onClose={() => setDeviceToRegister(null)}
                    unregistered={[
                        {
                            value: deviceToRegister.device_id,
                            label: `${deviceToRegister.serial} (${deviceToRegister.product_class})`,
                        },
                    ]}
                    lockedDeviceId={deviceToRegister.device_id}
                    pelanggans={pelanggans}
                />
            )}

            {onuToEdit && (
                <OnuDialog
                    key={`edit-${onuToEdit.device_id}`}
                    open
                    onOpenChange={(open) => !open && setOnuToEdit(null)}
                    onClose={() => setOnuToEdit(null)}
                    unregistered={[]}
                    device={{ ...onuToEdit, genieacs_id: onuToEdit.device_id }}
                    pelanggans={pelanggans}
                />
            )}

            <Dialog
                open={onuToDelete !== null}
                onOpenChange={(open) => !open && setOnuToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Lepas pemetaan?</DialogTitle>
                        <DialogDescription>
                            Pemetaan {onuToDelete?.customer_name} akan dihapus.
                            Perangkat tetap ada di GenieACS.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Batal</Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            onClick={() => {
                                const targetId = onuToDelete?.onu_id;

                                if (!targetId) {
                                    return;
                                }

                                router.delete(onuDestroy(targetId), {
                                    preserveScroll: true,
                                    onSuccess: () => setOnuToDelete(null),
                                });
                            }}
                        >
                            Hapus
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function OnuDialog({
    open,
    onOpenChange,
    onClose,
    unregistered,
    device,
    lockedDeviceId,
    pelanggans = [],
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    unregistered: OnuOption[];
    device?: OnuDeviceRow;
    lockedDeviceId?: string;
    pelanggans?: {
        id: number;
        name: string;
        whatsapp: string;
        secret_username: string | null;
    }[];
}) {
    const [nameQuery, setNameQuery] = useState(device?.customer_name ?? '');
    const [pppoeUser, setPppoeUser] = useState(device?.pppoe_user ?? '');

    const nameMatches =
        nameQuery.trim().length > 0
            ? pelanggans
                  .filter((option) =>
                      option.name
                          .toLowerCase()
                          .includes(nameQuery.trim().toLowerCase()),
                  )
                  .slice(0, 6)
            : [];

    const applyPelanggan = (option: {
        name: string;
        secret_username: string | null;
    }) => {
        setNameQuery(option.name);

        if (option.secret_username) {
            setPppoeUser(option.secret_username);
        }
    };
    const id = device?.onu_id ?? 'new';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {device ? 'Ubah Data ONU' : 'Daftarkan ONU'}
                    </DialogTitle>
                    <DialogDescription>
                        Hubungkan perangkat GenieACS dengan data pelanggan.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(device
                        ? onuUpdate.form(device.onu_id as number)
                        : onuStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {lockedDeviceId ? (
                                <input
                                    type="hidden"
                                    name="genieacs_id"
                                    value={lockedDeviceId}
                                />
                            ) : device ? null : (
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-device`}>
                                        Perangkat
                                    </Label>

                                    <Select
                                        name="genieacs_id"
                                        required
                                        defaultValue={undefined}
                                    >
                                        <SelectTrigger
                                            id={`${id}-device`}
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Pilih perangkat belum terdaftar" />
                                        </SelectTrigger>
                                        <SelectContent
                                            position="popper"
                                            avoidCollisions
                                            align="start"
                                            className="max-h-none"
                                        >
                                            {unregistered.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <InputError message={errors.genieacs_id} />
                                </div>
                            )}

                            {device && (
                                <input
                                    type="hidden"
                                    name="genieacs_id"
                                    value={device.genieacs_id}
                                />
                            )}

                            <div className="relative grid gap-2">
                                <Label htmlFor={`${id}-name`}>
                                    Nama Pelanggan
                                </Label>

                                <Input
                                    id={`${id}-name`}
                                    name="name"
                                    required
                                    autoComplete="off"
                                    value={nameQuery}
                                    onChange={(e) => {
                                        setNameQuery(e.target.value);
                                    }}
                                    placeholder="Ketik untuk mencari pelanggan..."
                                />

                                {nameQuery.trim().length > 0 &&
                                    nameMatches.length > 0 && (
                                        <div className="absolute top-full z-20 mt-1 w-full overflow-hidden rounded-md border bg-popover shadow-md">
                                            {nameMatches.map((option) => (
                                                <button
                                                    key={option.id}
                                                    type="button"
                                                    className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-muted"
                                                    onClick={() =>
                                                        applyPelanggan(option)
                                                    }
                                                >
                                                    <span>{option.name}</span>
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {option.secret_username ??
                                                            '-'}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    )}

                                {nameQuery.trim().length > 0 &&
                                    nameMatches.length === 0 && (
                                        <p className="text-xs text-muted-foreground">
                                            Tidak ada pelanggan yang cocok.
                                        </p>
                                    )}

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-pppoe`}>
                                    User PPPoE
                                </Label>

                                <Input
                                    id={`${id}-pppoe`}
                                    name="pppoe_user"
                                    autoComplete="off"
                                    value={pppoeUser}
                                    onChange={(e) =>
                                        setPppoeUser(e.target.value)
                                    }
                                    placeholder="budi001 (opsional)"
                                />

                                <InputError message={errors.pppoe_user} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-desc`}>Keterangan</Label>

                                <Textarea
                                    id={`${id}-desc`}
                                    name="description"
                                    rows={2}
                                    defaultValue={device?.description ?? ''}
                                    placeholder="Alamat / catatan..."
                                />

                                <InputError message={errors.description} />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Batal
                                    </Button>
                                </DialogClose>

                                <Button disabled={processing}>Simpan</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

FtthOnu.layout = {
    breadcrumbs: [
        { title: 'FTTH', href: '/ftth/onu' },
        { title: 'ONU/ONT', href: '/ftth/onu' },
    ],
};
