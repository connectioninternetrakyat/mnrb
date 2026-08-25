import { Form, Head, router, useHttp, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Crosshair,
    MoreHorizontal,
    Pencil,
    Search,
    Trash2,
    UserRoundPlus,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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
    approve as prospectApprove,
    destroy as prospectDestroy,
    index as prospectIndex,
    reject as prospectReject,
    store as prospectStore,
    update as prospectUpdate,
} from '@/routes/prospects';
import type { Auth } from '@/types';
import type {
    ProspectFilters,
    ProspectPackageOption,
    ProspectRow,
    ProspectStatus,
} from '@/types';

type PageProps = {
    prospects: {
        data: ProspectRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    areas: { id: number; code: string; name: string }[];
    packages: ProspectPackageOption[];
    technicians: { id: number; name: string }[];
    filters: ProspectFilters;
};

const statusLabels: Record<ProspectStatus, string> = {
    menunggu_verifikasi: 'Menunggu Verifikasi',
    disetujui: 'Disetujui',
    ditolak: 'Ditolak',
};

const statusBadgeVariant: Record<
    ProspectStatus,
    'secondary' | 'default' | 'destructive'
> = {
    menunggu_verifikasi: 'secondary',
    disetujui: 'default',
    ditolak: 'destructive',
};

const formatPrice = (price: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(price);

const formatCoordinate = (value: number) => Number(value).toFixed(6);

export default function Prospects({
    prospects,
    areas,
    packages,
    technicians,
    filters,
}: PageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;

    const canApprove = auth.user.permissions.includes('prospects.approve');
    const canCreate = auth.user.permissions.includes('prospects.create');

    const [createOpen, setCreateOpen] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const submitFilter = (nextStatus?: string) => {
        const effectiveStatus = nextStatus ?? status;

        router.get(
            prospectIndex(),
            { search, status: effectiveStatus },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const [prospectToEdit, setProspectToEdit] = useState<ProspectRow | null>(
        null,
    );
    const [prospectToApprove, setProspectToApprove] =
        useState<ProspectRow | null>(null);
    const [approveTechnician, setApproveTechnician] = useState('');
    const [approveSchedule, setApproveSchedule] = useState('');
    const [prospectToReject, setProspectToReject] =
        useState<ProspectRow | null>(null);
    const [prospectToDelete, setProspectToDelete] =
        useState<ProspectRow | null>(null);

    const openApproveDialog = (prospect: ProspectRow) => {
        setProspectToApprove(prospect);
        setApproveTechnician(technicians.length === 1 ? String(technicians[0].id) : '');
        setApproveSchedule('');
    };

    const submitApproval = () => {
        if (!prospectToApprove || !approveTechnician || !approveSchedule) {
            return;
        }

        router.post(
            prospectApprove(prospectToApprove.id),
            {
                user_id: approveTechnician,
                scheduled_at: approveSchedule,
            },
            {
                preserveScroll: true,
                onSuccess: () => setProspectToApprove(null),
            },
        );
    };

    return (
        <>
            <Head title="Prospect" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Prospect"
                        description="Calon pelanggan dari marketing & teknisi. Setujui untuk membuat PPP Secret otomatis."
                    />

                    {canCreate && (
                        <Button onClick={() => setCreateOpen(true)}>
                            <UserRoundPlus />
                            Tambah Prospect
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitFilter();
                        }}
                        className="relative min-w-0 flex-1 sm:w-72 sm:flex-none"
                    >
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />

                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-8"
                            placeholder="Cari nama / wa / email / alamat..."
                        />
                    </form>

                    <Select
                        value={status || 'all'}
                        onValueChange={(value) => {
                            const nextStatus = value === 'all' ? '' : value;

                            setStatus(nextStatus);
                            submitFilter(nextStatus);
                        }}
                    >
                        <SelectTrigger className="w-[150px] shrink-0 sm:w-52">
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Status</SelectItem>
                            {Object.entries(statusLabels).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[960px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Calon Pelanggan
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    WhatsApp
                                </th>
                                <th className="px-4 py-3 font-medium">Area</th>
                                <th className="px-4 py-3 font-medium">Paket</th>
                                <th className="px-4 py-3 font-medium">
                                    Pembawa
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Lokasi
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {prospects.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada data prospect.
                                    </td>
                                </tr>
                            )}

                            {prospects.data.map((prospect) => (
                                <tr key={prospect.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {prospect.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {prospect.email}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {prospect.whatsapp}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <Badge variant="outline">
                                            {prospect.area_code}
                                        </Badge>
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {prospect.area_name}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <p className="font-medium">
                                            {prospect.package_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {prospect.package_price !== null &&
                                                formatPrice(
                                                    prospect.package_price,
                                                )}
                                            {prospect.rate_limit
                                                ? ` · ${prospect.rate_limit}`
                                                : ''}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {prospect.brought_by ?? '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {prospect.latitude !== null &&
                                        prospect.longitude !== null ? (
                                            <a
                                                href={`https://www.google.com/maps/search/${prospect.latitude},${prospect.longitude}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-primary underline-offset-2 hover:underline"
                                            >
                                                {formatCoordinate(
                                                    prospect.latitude,
                                                )}
                                                ,{' '}
                                                {formatCoordinate(
                                                    prospect.longitude,
                                                )}
                                            </a>
                                        ) : (
                                            '-'
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                statusBadgeVariant[
                                                    prospect.status
                                                ]
                                            }
                                        >
                                            {statusLabels[prospect.status]}
                                        </Badge>
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
                                                {canApprove &&
                                                    prospect.status ===
                                                        'menunggu_verifikasi' && (
                                                        <>
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    openApproveDialog(
                                                                        prospect,
                                                                    )
                                                                }
                                                            >
                                                                <CheckCircle2 />
                                                                Setujui
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    setProspectToReject(
                                                                        prospect,
                                                                    )
                                                                }
                                                            >
                                                                <XCircle />
                                                                Tolak
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                        </>
                                                    )}
                                                {canCreate &&
                                                    prospect.status ===
                                                        'menunggu_verifikasi' && (
                                                        <>
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    setProspectToEdit(
                                                                        prospect,
                                                                    )
                                                                }
                                                            >
                                                                <Pencil />
                                                                Ubah
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                        </>
                                                    )}
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setProspectToDelete(
                                                            prospect,
                                                        )
                                                    }
                                                    disabled={
                                                        prospect.status ===
                                                        'disetujui'
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

                {prospects.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Halaman {prospects.current_page} dari{' '}
                            {prospects.last_page} ({prospects.total} prospect)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!prospects.links[0]?.url}
                                onClick={() =>
                                    prospects.links[0]?.url &&
                                    router.visit(prospects.links[0].url, {
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
                                    !prospects.links[prospects.links.length - 1]
                                        ?.url
                                }
                                onClick={() =>
                                    prospects.links[prospects.links.length - 1]
                                        ?.url &&
                                    router.visit(
                                        prospects.links[
                                            prospects.links.length - 1
                                        ].url as string,
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

            <ProspectDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
                areas={areas}
                packages={packages}
                broughtBy={auth.user.name}
            />

            {prospectToEdit && (
                <ProspectDialog
                    key={`edit-${prospectToEdit.id}`}
                    open
                    onOpenChange={(open) => !open && setProspectToEdit(null)}
                    onClose={() => setProspectToEdit(null)}
                    areas={areas}
                    packages={packages}
                    broughtBy={prospectToEdit.brought_by ?? auth.user.name}
                    prospect={prospectToEdit}
                />
            )}

            <Dialog
                open={prospectToApprove !== null}
                onOpenChange={(open) => !open && setProspectToApprove(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Setujui & jadwalkan PSB</DialogTitle>
                        <DialogDescription>
                            Secret PPP{' '}
                            <code className="rounded bg-muted px-1 py-0.5 text-xs">
                                {prospectToApprove?.area_code}_{''}
                                {prospectToApprove?.name
                                    .toLowerCase()
                                    .replace(/[^a-z0-9]/g, '')}
                            </code>{' '}
                            dibuat otomatis (Radius aktif, Mikrotik disable)
                            dan tiket PSB langsung terjadwal.
                        </DialogDescription>
                    </DialogHeader>

                    {technicians.length === 0 ? (
                        <div className="flex items-center gap-2 rounded-md border border-dashed p-3 text-xs text-muted-foreground">
                            Belum ada petugas/Teknisi. Tambahkan pengguna dengan
                            role Teknisi terlebih dahulu.
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label>Petugas / Teknisi</Label>

                                <Select
                                    value={approveTechnician}
                                    onValueChange={setApproveTechnician}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih teknisi" />
                                    </SelectTrigger>
                                    <SelectContent
                                        position="popper"
                                        avoidCollisions
                                        align="start"
                                        className="max-h-none"
                                    >
                                        {technicians.map((tech) => (
                                            <SelectItem
                                                key={tech.id}
                                                value={String(tech.id)}
                                            >
                                                {tech.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="approve-schedule">
                                    Jadwal Pemasangan
                                </Label>

                                <Input
                                    id="approve-schedule"
                                    type="datetime-local"
                                    value={approveSchedule}
                                    onChange={(e) =>
                                        setApproveSchedule(e.target.value)
                                    }
                                />

                                <p className="text-xs text-muted-foreground">
                                    Otomatis menjadi jadwal tiket PSB untuk
                                    teknisi terpilih.
                                </p>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Batal</Button>
                        </DialogClose>

                        <Button
                            disabled={
                                technicians.length === 0 ||
                                !approveTechnician ||
                                !approveSchedule
                            }
                            onClick={submitApproval}
                        >
                            <CheckCircle2 />
                            Setujui & Jadwalkan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={prospectToReject !== null}
                onOpenChange={(open) => !open && setProspectToReject(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tolak prospect?</DialogTitle>
                        <DialogDescription>
                            Prospect{' '}
                            <span className="font-medium">
                                {prospectToReject?.name}
                            </span>{' '}
                            akan ditandai ditolak tanpa membuat secret PPP.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Batal</Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            onClick={() =>
                                prospectToReject &&
                                router.post(
                                    prospectReject(prospectToReject.id),
                                    undefined,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setProspectToReject(null),
                                    },
                                )
                            }
                        >
                            <XCircle />
                            Tolak
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={prospectToDelete !== null}
                onOpenChange={(open) => !open && setProspectToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus prospect?</DialogTitle>
                        <DialogDescription>
                            Data prospect {prospectToDelete?.name} akan dihapus
                            permanen. Tindakan ini tidak dapat dibatalkan.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Batal</Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            onClick={() =>
                                prospectToDelete &&
                                router.delete(
                                    prospectDestroy(prospectToDelete.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setProspectToDelete(null),
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

function ProspectDialog({
    open,
    onOpenChange,
    onClose,
    areas,
    packages,
    broughtBy,
    prospect,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    areas: { id: number; code: string; name: string }[];
    packages: ProspectPackageOption[];
    broughtBy: string;
    prospect?: ProspectRow;
}) {
    const id = prospect?.id ?? 'new';

    const http = useHttp({
        url: '',
    });

    const [latitude, setLatitude] = useState<string>(
        prospect?.latitude !== null && prospect?.latitude !== undefined
            ? String(prospect.latitude)
            : '',
    );
    const [longitude, setLongitude] = useState<string>(
        prospect?.longitude !== null && prospect?.longitude !== undefined
            ? String(prospect.longitude)
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

                    toast.success('Koordinat berhasil diambil.');
                } else {
                    toast.error('Koordinat tidak ditemukan dari URL tersebut.');
                }
            },
        }).catch(() => {
            toast.error(
                'Gagal mengambil lokasi. Periksa URL Google Maps lalu coba lagi.',
            );
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {prospect ? 'Ubah Prospect' : 'Tambah Prospect'}
                    </DialogTitle>
                    <DialogDescription>
                        Data calon pelanggan akan diverifikasi admin sebelum
                        dibuatkan PPP Secret.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(prospect
                        ? prospectUpdate.form(prospect.id)
                        : prospectStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="scrollbar-slim max-h-[62vh] space-y-4 overflow-x-hidden overflow-y-auto pr-1"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-name`}>
                                    Nama Lengkap
                                </Label>

                                <Input
                                    id={`${id}-name`}
                                    name="name"
                                    required
                                    autoFocus
                                    defaultValue={prospect?.name}
                                    placeholder="Contoh: Nadia Putri"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-email`}>Email</Label>

                                    <Input
                                        id={`${id}-email`}
                                        name="email"
                                        type="email"
                                        required
                                        defaultValue={prospect?.email}
                                        placeholder="nama@email.com"
                                    />

                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-wa`}>
                                        No WhatsApp
                                    </Label>

                                    <Input
                                        id={`${id}-wa`}
                                        name="whatsapp"
                                        type="tel"
                                        required
                                        defaultValue={prospect?.whatsapp}
                                        placeholder="081234567890 / +6281234567890"
                                    />

                                    <InputError message={errors.whatsapp} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-address`}>
                                    Alamat Lengkap
                                </Label>

                                <Textarea
                                    id={`${id}-address`}
                                    name="address"
                                    rows={2}
                                    required
                                    defaultValue={prospect?.address}
                                    placeholder="Jalan, RT/RW, kelurahan, kecamatan..."
                                />

                                <InputError message={errors.address} />
                            </div>

                            <div className="space-y-2 rounded-xl border p-3">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-maps`}>
                                        URL Google Maps
                                    </Label>

                                    <Input
                                        id={`${id}-maps`}
                                        name="maps_url"
                                        defaultValue={prospect?.maps_url ?? ''}
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

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-area`}>Area</Label>

                                    <Select
                                        name="area_code_id"
                                        defaultValue={
                                            prospect
                                                ? String(prospect.area_code_id)
                                                : undefined
                                        }
                                        required
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
                                            {areas.map((area) => (
                                                <SelectItem
                                                    key={area.id}
                                                    value={String(area.id)}
                                                >
                                                    {area.code} — {area.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <InputError message={errors.area_code_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-package`}>
                                        Paket
                                    </Label>

                                    <Select
                                        name="package_id"
                                        defaultValue={
                                            prospect
                                                ? String(prospect.package_id)
                                                : undefined
                                        }
                                        required
                                    >
                                        <SelectTrigger
                                            id={`${id}-package`}
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Pilih paket" />
                                        </SelectTrigger>
                                        <SelectContent
                                            position="popper"
                                            avoidCollisions
                                            align="start"
                                            className="max-h-none"
                                        >
                                            {packages.map((item) => (
                                                <SelectItem
                                                    key={item.id}
                                                    value={String(item.id)}
                                                >
                                                    {item.name}
                                                    {' · '}
                                                    {formatPrice(item.price)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <InputError message={errors.package_id} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-brought-by`}>
                                    Marketing / Pembawa
                                </Label>

                                <Input
                                    id={`${id}-brought-by`}
                                    value={broughtBy}
                                    readOnly
                                    disabled
                                />

                                <p className="text-xs text-muted-foreground">
                                    Otomatis terisi dari pengguna yang login.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-note`}>
                                    Catatan{' '}
                                    <span className="text-muted-foreground">
                                        (opsional)
                                    </span>
                                </Label>

                                <Textarea
                                    id={`${id}-note`}
                                    name="note"
                                    rows={2}
                                    defaultValue={prospect?.note ?? ''}
                                    placeholder="Catatan tambahan..."
                                />

                                <InputError message={errors.note} />
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

Prospects.layout = {
    breadcrumbs: [
        {
            title: 'Prospect',
            href: '/prospects',
        },
    ],
};
