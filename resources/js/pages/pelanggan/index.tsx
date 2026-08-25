import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    Eye,
    MoreHorizontal,
    Pencil,
    PlayCircle,
    Search,
    StopCircle,
    Trash2,
    Users,
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
    activate as pelangganActivate,
    destroy as pelangganDestroy,
    index as pelangganIndex,
    show as pelangganShow,
    stop as pelangganStop,
    store as pelangganStore,
    update as pelangganUpdate,
} from '@/routes/pelanggan';
import type { Auth } from '@/types';

type PelangganRow = {
    id: number;
    name: string;
    email: string | null;
    whatsapp: string;
    address: string;
    maps_url: string | null;
    area_code_id: number;
    area_code: string | null;
    area_name: string | null;
    package_id: number;
    package_name: string | null;
    package_price: number | null;
    status: 'aktif' | 'berhenti';
    joined_at: string | null;
    activated_at: string | null;
    notes: string | null;
    secret_username: string | null;
    secret_profile: string | null;
    secret_disabled: boolean;
    source: 'prospek' | 'manual';
    ticket_number: string | null;
    ticket_status: string | null;
    prospect_name: string | null;
    isolated: boolean;
    invoice_number: string | null;
    invoice_status: string | null;
    invoice_total: number;
    bills_count: number;
    bills_unpaid: number;
    outstanding: number;
};

type SecretOption = { id: number; name: string; profile: string | null };
type Option = { id: number; code?: string; name: string };
type PackageOption = { id: number; name: string; price: number };

type PageProps = {
    pelanggans: {
        data: PelangganRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    areas: Option[];
    packages: PackageOption[];
    availableSecrets: SecretOption[];
    filters: { search?: string | null; status?: string | null };
    auth: Auth;
};

const rupiah = (value: number) =>
    `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;

export default function Pelanggans({
    pelanggans,
    areas,
    packages,
    availableSecrets,
    filters,
}: PageProps) {
    const { auth } = usePage<PageProps>().props;

    const canUpdate = auth.user.permissions.includes('pelanggan.update');
    const canDelete = auth.user.permissions.includes('pelanggan.delete');

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<PelangganRow | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<PelangganRow | null>(null);

    const submitFilter = (nextStatus?: string) => {
        router.get(
            pelangganIndex(),
            { search, status: nextStatus ?? status },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Pelanggan" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Pelanggan"
                        description="Pelanggan FTTH aktif — otomatis terbentuk saat prospect disetujui."
                    />

                    {canUpdate && (
                        <Button onClick={() => setCreateOpen(true)}>
                            <Users />
                            Tambah Pelanggan
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
                            placeholder="Cari nama / wa / username pppoe..."
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
                        <SelectTrigger className="w-[150px] shrink-0 sm:w-48">
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Status</SelectItem>
                            <SelectItem value="aktif">Aktif</SelectItem>
                            <SelectItem value="berhenti">Berhenti</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[900px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Nama</th>
                                <th className="px-4 py-3 font-medium">Area</th>
                                <th className="px-4 py-3 font-medium">Paket</th>
                                <th className="px-4 py-3 font-medium">
                                    Akun PPPoE
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Tagihan
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Bergabung
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {pelanggans.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada pelanggan.
                                    </td>
                                </tr>
                            )}

                            {pelanggans.data.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {item.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.whatsapp}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {item.area_code ? (
                                            <Badge variant="outline">
                                                {item.area_code}
                                            </Badge>
                                        ) : (
                                            '-'
                                        )}
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {item.area_name}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <p>{item.package_name || '-'}</p>
                                        {item.package_price !== null && (
                                            <p className="text-xs text-muted-foreground">
                                                {rupiah(item.package_price)}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs whitespace-nowrap">
                                        {item.secret_username ? (
                                            <>
                                                {item.secret_username}
                                                {item.secret_disabled && (
                                                    <Badge
                                                        variant="destructive"
                                                        className="ml-2"
                                                    >
                                                        off
                                                    </Badge>
                                                )}
                                            </>
                                        ) : (
                                            '-'
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-xs whitespace-nowrap">
                                        {item.source === 'manual' ? (
                                            <span className="text-muted-foreground">
                                                manual
                                            </span>
                                        ) : (
                                            <>
                                                <p className="font-mono">
                                                    {item.ticket_number}
                                                </p>
                                                {item.isolated ? (
                                                    <Badge
                                                        variant="destructive"
                                                        className="mt-1"
                                                    >
                                                        Terisolir
                                                    </Badge>
                                                ) : item.outstanding > 0 ? (
                                                    <p className="text-muted-foreground">
                                                        Tunggakan{' '}
                                                        {rupiah(
                                                            item.outstanding,
                                                        )}
                                                    </p>
                                                ) : (
                                                    <p className="text-muted-foreground">
                                                        Lunas
                                                    </p>
                                                )}
                                            </>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-xs whitespace-nowrap text-muted-foreground">
                                        {formatDate(item.joined_at)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                item.status === 'aktif'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {item.status === 'aktif'
                                                ? 'Aktif'
                                                : 'Berhenti'}
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
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        router.visit(
                                                            pelangganShow(
                                                                item.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Eye />
                                                    Detail
                                                </DropdownMenuItem>
                                                {canUpdate && (
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setEditTarget(item)
                                                        }
                                                    >
                                                        <Pencil />
                                                        Ubah
                                                    </DropdownMenuItem>
                                                )}
                                                {canUpdate &&
                                                    item.secret_username && (
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                router.post(
                                                                    item.status ===
                                                                        'aktif'
                                                                        ? pelangganStop(
                                                                              item.id,
                                                                          )
                                                                        : pelangganActivate(
                                                                              item.id,
                                                                          ),
                                                                    undefined,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            {item.status ===
                                                            'aktif' ? (
                                                                <StopCircle />
                                                            ) : (
                                                                <PlayCircle />
                                                            )}
                                                            {item.status ===
                                                            'aktif'
                                                                ? 'Hentikan Layanan'
                                                                : 'Aktifkan Layanan'}
                                                        </DropdownMenuItem>
                                                    )}
                                                {canDelete && (
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setDeleteTarget(
                                                                item,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                        Hapus
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

                {pelanggans.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Halaman {pelanggans.current_page} dari{' '}
                            {pelanggans.last_page} ({pelanggans.total}{' '}
                            pelanggan)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!pelanggans.links[0]?.url}
                                onClick={() =>
                                    pelanggans.links[0]?.url &&
                                    router.visit(pelanggans.links[0].url, {
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
                                    !pelanggans.links[
                                        pelanggans.links.length - 1
                                    ]?.url
                                }
                                onClick={() =>
                                    pelanggans.links[
                                        pelanggans.links.length - 1
                                    ]?.url &&
                                    router.visit(
                                        pelanggans.links[
                                            pelanggans.links.length - 1
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

            <PelangganFormDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
                areas={areas}
                packages={packages}
                secrets={availableSecrets}
            />

            {editTarget && (
                <PelangganFormDialog
                    key={`edit-${editTarget.id}`}
                    open
                    onOpenChange={(open) => !open && setEditTarget(null)}
                    onClose={() => setEditTarget(null)}
                    areas={areas}
                    packages={packages}
                    secrets={availableSecrets}
                    pelanggan={editTarget}
                />
            )}

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus pelanggan?</DialogTitle>
                        <DialogDescription>
                            Data {deleteTarget?.name} akan dihapus permanen.
                            Tindakan ini tidak dapat dibatalkan.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Batal</Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            onClick={() =>
                                deleteTarget &&
                                router.delete(
                                    pelangganDestroy(deleteTarget.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setDeleteTarget(null),
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

function PelangganFormDialog({
    open,
    onOpenChange,
    onClose,
    areas,
    packages,
    secrets,
    pelanggan,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    areas: Option[];
    packages: PackageOption[];
    secrets: SecretOption[];
    pelanggan?: PelangganRow;
}) {
    const id = pelanggan?.id ?? 'new';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {pelanggan ? 'Ubah Pelanggan' : 'Tambah Pelanggan'}
                    </DialogTitle>
                    <DialogDescription>
                        Data pelanggan layanan FTTH. Akun PPPoE bisa ditautkan
                        dari daftar akun yang belum terpakai.
                    </DialogDescription>
                </DialogHeader>

                {pelanggan && pelanggan.source === 'prospek' && (
                    <div className="rounded-lg border bg-muted/40 px-3 py-2 text-xs">
                        <p className="mb-1 font-medium">
                            Integrasi Prospek &amp; Billing
                        </p>
                        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-muted-foreground">
                            <dt>Asal</dt>
                            <dd className="text-right">
                                {pelanggan.prospect_name ?? '-'}
                            </dd>
                            <dt>Tiket PSB</dt>
                            <dd className="text-right font-mono">
                                {pelanggan.ticket_number ?? '-'} ·{' '}
                                {pelanggan.ticket_status ?? '-'}
                            </dd>
                            <dt>Invoice aktivasi</dt>
                            <dd className="text-right">
                                {pelanggan.invoice_number ? (
                                    <>
                                        {pelanggan.invoice_status === 'lunas'
                                            ? 'Lunas'
                                            : 'Menunggu'}{' '}
                                        ({rupiah(pelanggan.invoice_total)})
                                    </>
                                ) : (
                                    '-'
                                )}
                            </dd>
                            <dt>Tagihan bulanan</dt>
                            <dd className="text-right">
                                {pelanggan.bills_count} tagihan ·{' '}
                                {pelanggan.bills_unpaid} belum lunas (
                                {rupiah(pelanggan.outstanding)})
                            </dd>
                        </dl>
                    </div>
                )}

                <Form
                    {...(pelanggan
                        ? pelangganUpdate.form(pelanggan.id)
                        : pelangganStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="status"
                                value={pelanggan?.status ?? 'aktif'}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-name`}>Nama</Label>

                                <Input
                                    id={`${id}-name`}
                                    name="name"
                                    required
                                    defaultValue={pelanggan?.name}
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
                                        defaultValue={pelanggan?.email ?? ''}
                                    />

                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-wa`}>WhatsApp</Label>

                                    <Input
                                        id={`${id}-wa`}
                                        name="whatsapp"
                                        required
                                        defaultValue={pelanggan?.whatsapp}
                                        placeholder="08..."
                                    />

                                    <InputError message={errors.whatsapp} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-address`}>Alamat</Label>

                                <Textarea
                                    id={`${id}-address`}
                                    className="resize-none"
                                    name="address"
                                    rows={2}
                                    required
                                    defaultValue={pelanggan?.address}
                                />

                                <InputError message={errors.address} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-maps`}>
                                    URL Google Maps
                                </Label>

                                <Input
                                    id={`${id}-maps`}
                                    name="maps_url"
                                    type="url"
                                    placeholder="https://maps.app.goo.gl/..."
                                    defaultValue={pelanggan?.maps_url ?? ''}
                                />

                                <InputError message={errors.maps_url} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label>Area</Label>

                                    <Select
                                        name="area_code_id"
                                        defaultValue={
                                            pelanggan
                                                ? String(pelanggan.area_code_id)
                                                : undefined
                                        }
                                        required
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Pilih area" />
                                        </SelectTrigger>
                                        <SelectContent>
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
                                    <Label>Paket</Label>

                                    <Select
                                        name="package_id"
                                        defaultValue={
                                            pelanggan
                                                ? String(pelanggan.package_id)
                                                : undefined
                                        }
                                        required
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Pilih paket" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {packages.map((item) => (
                                                <SelectItem
                                                    key={item.id}
                                                    value={String(item.id)}
                                                >
                                                    {item.name} ·{' '}
                                                    {rupiah(item.price)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <InputError message={errors.package_id} />
                                </div>
                            </div>

                            {!pelanggan && (
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-secret`}>
                                        Akun PPPoE{' '}
                                        <span className="text-muted-foreground">
                                            (opsional)
                                        </span>
                                    </Label>

                                    <Select name="ppp_secret_id">
                                        <SelectTrigger
                                            id={`${id}-secret`}
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Tanpa akun PPPoE" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {secrets.map((secret) => (
                                                <SelectItem
                                                    key={secret.id}
                                                    value={String(secret.id)}
                                                >
                                                    {secret.name} ·{' '}
                                                    {secret.profile ?? '-'}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <InputError
                                        message={errors.ppp_secret_id}
                                    />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-notes`}>
                                    Catatan{' '}
                                    <span className="text-muted-foreground">
                                        (opsional)
                                    </span>
                                </Label>

                                <Textarea
                                    id={`${id}-notes`}
                                    className="resize-none"
                                    name="notes"
                                    rows={2}
                                    defaultValue={pelanggan?.notes ?? ''}
                                />

                                <InputError message={errors.notes} />
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

function formatDate(value: string | null) {
    return value === null
        ? '-'
        : new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(new Date(value));
}

Pelanggans.layout = {
    breadcrumbs: [
        {
            title: 'Pelanggan',
            href: '/pelanggan',
        },
    ],
};
