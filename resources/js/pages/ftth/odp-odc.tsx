import { Form, Head, router, useHttp } from '@inertiajs/react';
import {
    Crosshair,
    MapPinned,
    MoreHorizontal,
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
import { resolve as ftthMapsResolve } from '@/routes/ftth/maps';
import {
    destroy as odpOdcDestroy,
    store as odpOdcStore,
    update as odpOdcUpdate,
} from '@/routes/ftth/odp-odc';
import type { AreaCodeOption, OdpOdcRow } from '@/types';

type PageProps = {
    devices: OdpOdcRow[];
    areas: AreaCodeOption[];
};

export default function FtthOdpOdc({ devices, areas }: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [deviceToEdit, setDeviceToEdit] = useState<OdpOdcRow | null>(null);
    const [deviceToDelete, setDeviceToDelete] = useState<OdpOdcRow | null>(
        null,
    );

    return (
        <>
            <Head title="ODP/ODC" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="ODP/ODC"
                        description="Kelola titik distribusi FTTH beserta lokasinya."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Tambah ODP/ODC
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[860px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Kode</th>
                                <th className="px-4 py-3 font-medium">Nama</th>
                                <th className="px-4 py-3 font-medium">Area</th>
                                <th className="px-4 py-3 font-medium">
                                    Wilayah
                                </th>
                                <th className="px-4 py-3 font-medium">Port</th>
                                <th className="px-4 py-3 font-medium">
                                    Splitter
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Koordinat
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {devices.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada data ODP/ODC.
                                    </td>
                                </tr>
                            )}

                            {devices.map((device) => (
                                <tr key={device.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <MapPinned className="size-4 shrink-0 text-muted-foreground" />
                                            <Badge variant="secondary">
                                                {device.code}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {device.name}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {device.area_code ?? '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {device.region || '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {device.port ?? '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {device.splitter_ratio || '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {device.latitude !== null &&
                                        device.longitude !== null ? (
                                            <a
                                                href={`https://www.google.com/maps/search/${device.latitude},${device.longitude}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-primary underline-offset-2 hover:underline"
                                            >
                                                {device.latitude.toFixed(6)},{' '}
                                                {device.longitude.toFixed(6)}
                                            </a>
                                        ) : (
                                            '-'
                                        )}
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
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setDeviceToEdit(device)
                                                    }
                                                >
                                                    <UserRoundPen />
                                                    Ubah
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setDeviceToDelete(
                                                            device,
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                    Hapus
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <OdpOdcDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
                areas={areas}
            />

            {deviceToEdit && (
                <OdpOdcDialog
                    key={`edit-${deviceToEdit.id}`}
                    open
                    onOpenChange={(open) => !open && setDeviceToEdit(null)}
                    onClose={() => setDeviceToEdit(null)}
                    areas={areas}
                    device={deviceToEdit}
                />
            )}

            <Dialog
                open={deviceToDelete !== null}
                onOpenChange={(open) => !open && setDeviceToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus ODP/ODC?</DialogTitle>
                        <DialogDescription>
                            {deviceToDelete?.code} ({deviceToDelete?.name}) akan
                            dihapus permanen. Tindakan ini tidak dapat
                            dibatalkan.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Batal</Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            onClick={() =>
                                deviceToDelete &&
                                router.delete(
                                    odpOdcDestroy(deviceToDelete.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setDeviceToDelete(null),
                                    },
                                )
                            }
                        >
                            Hapus
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function OdpOdcDialog({
    open,
    onOpenChange,
    onClose,
    areas,
    device,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    areas: AreaCodeOption[];
    device?: OdpOdcRow;
}) {
    const id = device?.id ?? 'new';

    const http = useHttp({
        url: '',
    });

    const [latitude, setLatitude] = useState<string>(
        device?.latitude !== null && device?.latitude !== undefined
            ? String(device.latitude)
            : '',
    );
    const [longitude, setLongitude] = useState<string>(
        device?.longitude !== null && device?.longitude !== undefined
            ? String(device.longitude)
            : '',
    );

    const resolveLocation = () => {
        http.clearErrors();
        http.setData(
            'url',
            (document.getElementById(`${id}-maps`) as HTMLInputElement)
                ?.value ?? '',
        );

        http.post(ftthMapsResolve().url, {
            onSuccess: (data) => {
                const coordinates = data as {
                    latitude?: number;
                    longitude?: number;
                } | null;

                if (
                    coordinates?.latitude !== undefined &&
                    coordinates?.longitude !== undefined
                ) {
                    setLatitude(String(coordinates.latitude));
                    setLongitude(String(coordinates.longitude));
                }
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {device ? 'Ubah ODP/ODC' : 'Tambah ODP/ODC'}
                    </DialogTitle>
                    <DialogDescription>
                        {device
                            ? `Perbarui data ${device.code}.`
                            : 'Tambahkan titik ODP atau ODC baru.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(device
                        ? odpOdcUpdate.form(device.id)
                        : odpOdcStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="scrollbar-slim max-h-[60vh] space-y-4 overflow-x-hidden overflow-y-auto pr-1"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-code`}>Kode</Label>

                                    <Input
                                        id={`${id}-code`}
                                        name="code"
                                        required
                                        autoFocus
                                        defaultValue={device?.code}
                                        placeholder="Contoh: ODP-TCL01-001"
                                    />

                                    <InputError message={errors.code} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-name`}>Nama</Label>

                                    <Input
                                        id={`${id}-name`}
                                        name="name"
                                        required
                                        defaultValue={device?.name}
                                        placeholder="Nama titik"
                                    />

                                    <InputError message={errors.name} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-area`}>Area</Label>

                                <Select
                                    name="area_code_id"
                                    defaultValue={
                                        device
                                            ? String(device.area_code_id)
                                            : undefined
                                    }
                                >
                                    <SelectTrigger
                                        id={`${id}-area`}
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pilih kode area" />
                                    </SelectTrigger>
                                    <SelectContent
                                        position="popper"
                                        avoidCollisions
                                        align="start"
                                        className="max-h-none"
                                    >
                                        {areas.length === 0 ? (
                                            <p className="px-3 py-2 text-sm text-muted-foreground">
                                                Belum ada kode area — buat dulu
                                                di menu Kode Area.
                                            </p>
                                        ) : (
                                            areas.map((area) => (
                                                <SelectItem
                                                    key={area.id}
                                                    value={String(area.id)}
                                                >
                                                    {area.code} — {area.name}
                                                </SelectItem>
                                            ))
                                        )}
                                    </SelectContent>
                                </Select>

                                <InputError message={errors.area_code_id} />
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-region`}>
                                        Wilayah
                                    </Label>

                                    <Input
                                        id={`${id}-region`}
                                        name="region"
                                        defaultValue={device?.region ?? ''}
                                        placeholder="Wilayah"
                                    />

                                    <InputError message={errors.region} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-port`}>Port</Label>

                                    <Input
                                        id={`${id}-port`}
                                        name="port"
                                        type="number"
                                        min={1}
                                        max={96}
                                        defaultValue={device?.port ?? undefined}
                                        placeholder="8"
                                    />

                                    <InputError message={errors.port} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-ratio`}>
                                        Rasio Splitter
                                    </Label>

                                    <Input
                                        id={`${id}-ratio`}
                                        name="splitter_ratio"
                                        defaultValue={
                                            device?.splitter_ratio ?? ''
                                        }
                                        placeholder="1:8"
                                    />

                                    <InputError
                                        message={errors.splitter_ratio}
                                    />
                                </div>
                            </div>

                            <div className="space-y-2 rounded-xl border p-3">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-maps`}>
                                        URL Google Maps
                                    </Label>

                                    <Input
                                        id={`${id}-maps`}
                                        name="maps_url"
                                        defaultValue={device?.maps_url ?? ''}
                                        placeholder="https://maps.app.goo.gl/..."
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        Tempel link bagikan dari Google Maps
                                        (boleh shortlink), lalu klik Ambil
                                        Lokasi.
                                    </p>

                                    <InputError message={errors.maps_url} />
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={http.processing}
                                    onClick={resolveLocation}
                                >
                                    <Crosshair />
                                    {http.processing
                                        ? 'Mengambil...'
                                        : 'Ambil Lokasi'}
                                </Button>

                                <InputError message={http.errors.url} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-lat`}>
                                        Latitude
                                    </Label>

                                    <Input
                                        id={`${id}-lat`}
                                        name="latitude"
                                        value={latitude}
                                        onChange={(e) =>
                                            setLatitude(e.target.value)
                                        }
                                        inputMode="decimal"
                                        placeholder="-7.154157"
                                    />

                                    <InputError message={errors.latitude} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-lng`}>
                                        Longitude
                                    </Label>

                                    <Input
                                        id={`${id}-lng`}
                                        name="longitude"
                                        value={longitude}
                                        onChange={(e) =>
                                            setLongitude(e.target.value)
                                        }
                                        inputMode="decimal"
                                        placeholder="108.333915"
                                    />

                                    <InputError message={errors.longitude} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-desc`}>Keterangan</Label>

                                <Textarea
                                    id={`${id}-desc`}
                                    name="description"
                                    rows={2}
                                    defaultValue={device?.description ?? ''}
                                    placeholder="Keterangan tambahan..."
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

FtthOdpOdc.layout = {
    breadcrumbs: [
        {
            title: 'FTTH',
            href: '/ftth/odp-odcs',
        },
        {
            title: 'ODP/ODC',
            href: '/ftth/odp-odcs',
        },
    ],
};
