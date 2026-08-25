import { Form, Head, router } from '@inertiajs/react';
import {
    IdCard,
    MoreHorizontal,
    PackageSearch,
    Pencil,
    Search,
    Trash2,
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
    destroy as packageDestroy,
    index as packageIndex,
    store as packageStore,
    update as packageUpdate,
} from '@/routes/packages';
import type {
    PackagePaginated,
    PackageProfileOption,
    PackageRow,
    PppFilters,
    PppServerBadge,
} from '@/types';

type PageProps = {
    packages: PackagePaginated<PackageRow>;
    profiles: PackageProfileOption[];
    filters: PppFilters;
    mikrotik_server: PppServerBadge;
    radius_server: PppServerBadge;
};

const formatPrice = (price: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(price);

export default function Packages({
    packages,
    profiles,
    filters,
    mikrotik_server: mikrotikServer,
}: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            packageIndex(),
            { search },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };
    const [packageToEdit, setPackageToEdit] = useState<PackageRow | null>(null);
    const [packageToDelete, setPackageToDelete] = useState<PackageRow | null>(
        null,
    );

    const noProfiles = profiles.length === 0;

    return (
        <>
            <Head title="Paket & Harga" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Paket & Harga"
                        description="Kelola paket layanan dan harga, terhubung langsung ke PPP Profile Mikrotik."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Tambah Paket
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-4 rounded-xl border p-4 text-sm">
                    <div className="flex items-center gap-2">
                        <IdCard className="size-4 shrink-0 text-muted-foreground" />
                        Mikrotik:
                        {mikrotikServer ? (
                            <Badge variant="secondary">
                                {mikrotikServer.name}
                            </Badge>
                        ) : (
                            <span className="text-destructive">belum ada</span>
                        )}
                    </div>

                    <p className="text-xs text-muted-foreground md:ml-auto">
                        Paket terhubung ke PPP Profile pada server utama.
                    </p>
                </div>

                <form
                    onSubmit={submitSearch}
                    className="relative w-full sm:w-72"
                >
                    <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />

                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="pl-8"
                        placeholder="Cari paket / profil / rate..."
                    />
                </form>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Nama Paket
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    PPP Profile
                                </th>
                                <th className="px-4 py-3 font-medium">Harga</th>
                                <th className="px-4 py-3 font-medium">
                                    Deskripsi
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {noProfiles && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada PPP Profile. Buka menu{' '}
                                        <span className="font-medium">
                                            PPPoE → PPP Profile
                                        </span>{' '}
                                        lalu klik "Sync dari Mikrotik" untuk
                                        mengimpor profil.
                                    </td>
                                </tr>
                            )}

                            {!noProfiles &&
                                packages.data.length === 0 &&
                                !search && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-10 text-center text-muted-foreground"
                                        >
                                            Belum ada paket. Klik "Tambah Paket"
                                            untuk membuat harga dari profil yang
                                            tersedia.
                                        </td>
                                    </tr>
                                )}

                            {search && packages.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Tidak ada paket yang cocok dengan
                                        pencarian.
                                    </td>
                                </tr>
                            )}

                            {packages.data.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {item.name}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <span className="font-medium">
                                            {item.profile_name}
                                        </span>
                                        {item.rate_limit && (
                                            <Badge
                                                variant="outline"
                                                className="ml-2"
                                            >
                                                {item.rate_limit}
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-semibold whitespace-nowrap">
                                        {formatPrice(item.price)}
                                    </td>
                                    <td className="max-w-[240px] truncate px-4 py-3">
                                        {item.description || '-'}
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
                                                        setPackageToEdit(item)
                                                    }
                                                >
                                                    <Pencil />
                                                    Ubah
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setPackageToDelete(item)
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

                {packages.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Halaman {packages.current_page} dari{' '}
                            {packages.last_page} ({packages.total} paket)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!packages.links[0]?.url}
                                onClick={() =>
                                    packages.links[0]?.url &&
                                    router.visit(packages.links[0].url, {
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
                                    !packages.links[packages.links.length - 1]
                                        ?.url
                                }
                                onClick={() =>
                                    packages.links[packages.links.length - 1]
                                        ?.url &&
                                    router.visit(
                                        packages.links[
                                            packages.links.length - 1
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

            <PackageDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
                profiles={profiles}
            />

            {packageToEdit && (
                <PackageDialog
                    key={`edit-${packageToEdit.id}`}
                    open
                    onOpenChange={(open) => !open && setPackageToEdit(null)}
                    onClose={() => setPackageToEdit(null)}
                    profiles={profiles}
                    item={packageToEdit}
                />
            )}

            <Dialog
                open={packageToDelete !== null}
                onOpenChange={(open) => !open && setPackageToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus paket?</DialogTitle>
                        <DialogDescription>
                            Paket {packageToDelete?.name} akan dihapus. PPP
                            Profile terkait tetap aman di Mikrotik & Radius.
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
                                packageToDelete &&
                                router.delete(
                                    packageDestroy(packageToDelete.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setPackageToDelete(null),
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

function PackageDialog({
    open,
    onOpenChange,
    onClose,
    profiles,
    item,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    profiles: PackageProfileOption[];
    item?: PackageRow;
}) {
    const id = item?.id ?? 'new';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {item ? 'Ubah Paket' : 'Tambah Paket'}
                    </DialogTitle>
                    <DialogDescription>
                        Paket ditautkan ke PPP Profile nyata dari Mikrotik.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(item
                        ? packageUpdate.form(item.id)
                        : packageStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-name`}>Nama</Label>

                                <Input
                                    id={`${id}-name`}
                                    name="name"
                                    required
                                    autoFocus
                                    defaultValue={item?.name}
                                    placeholder="Contoh: Paket Home 10M"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-profile`}>
                                    PPP Profile
                                </Label>

                                <Select
                                    name="ppp_profile_id"
                                    defaultValue={
                                        item?.ppp_profile_id !== undefined
                                            ? String(item.ppp_profile_id)
                                            : undefined
                                    }
                                    required
                                >
                                    <SelectTrigger
                                        id={`${id}-profile`}
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pilih profil" />
                                    </SelectTrigger>
                                    <SelectContent
                                        position="popper"
                                        avoidCollisions
                                        align="start"
                                        className="max-h-none"
                                    >
                                        {profiles.map((profile) => {
                                            const ownedByThis =
                                                item?.ppp_profile_id ===
                                                profile.id;

                                            return (
                                                <SelectItem
                                                    key={profile.id}
                                                    value={String(profile.id)}
                                                    disabled={
                                                        profile.has_package &&
                                                        !ownedByThis
                                                    }
                                                >
                                                    {profile.name}
                                                    {profile.rate_limit
                                                        ? ` · ${profile.rate_limit}`
                                                        : ''}
                                                    {profile.has_package &&
                                                    !ownedByThis
                                                        ? ' · sudah dipakai'
                                                        : ''}
                                                </SelectItem>
                                            );
                                        })}
                                    </SelectContent>
                                </Select>

                                <InputError message={errors.ppp_profile_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-price`}>
                                    Harga (Rp)
                                </Label>

                                <Input
                                    id={`${id}-price`}
                                    name="price"
                                    type="number"
                                    min="0"
                                    step="1"
                                    required
                                    defaultValue={item?.price ?? ''}
                                    placeholder="150000"
                                />

                                <InputError message={errors.price} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-description`}>
                                    Deskripsi
                                </Label>

                                <Textarea
                                    id={`${id}-description`}
                                    name="description"
                                    rows={3}
                                    defaultValue={item?.description ?? ''}
                                    placeholder="Contoh: Unlimited 10 Mbps untuk rumah tangga"
                                />

                                <InputError message={errors.description} />
                            </div>

                            {profiles.length === 0 && (
                                <div className="flex items-center gap-2 rounded-md border border-dashed p-3 text-xs text-muted-foreground">
                                    <PackageSearch className="size-4 shrink-0" />
                                    Tidak ada PPP Profile tersedia. Sinkronkan
                                    dulu di menu PPPoE → PPP Profile.
                                </div>
                            )}

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Batal
                                    </Button>
                                </DialogClose>

                                <Button
                                    disabled={
                                        processing || profiles.length === 0
                                    }
                                >
                                    Simpan
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

Packages.layout = {
    breadcrumbs: [
        {
            title: 'Paket & Harga',
            href: '/packages',
        },
    ],
};
