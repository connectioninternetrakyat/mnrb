import { Form, Head, router } from '@inertiajs/react';
import {
    IdCard,
    MoreHorizontal,
    RefreshCw,
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
    destroy as pppProfileDestroy,
    index as pppProfileIndex,
    store as pppProfileStore,
    sync as pppProfileSync,
    update as pppProfileUpdate,
} from '@/routes/ppp/profile';
import type {
    PppFilters,
    PppPaginated,
    PppProfileRow,
    PppServerBadge,
} from '@/types';

type PageProps = {
    profiles: PppPaginated<PppProfileRow>;
    filters: PppFilters;
    mikrotik_server: PppServerBadge;
    radius_server: PppServerBadge;
};

export default function PppProfiles({
    profiles,
    filters,
    mikrotik_server: mikrotikServer,
    radius_server: radiusServer,
}: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            pppProfileIndex(),
            { search },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };
    const [profileToEdit, setProfileToEdit] = useState<PppProfileRow | null>(
        null,
    );
    const [profileToDelete, setProfileToDelete] =
        useState<PppProfileRow | null>(null);

    return (
        <>
            <Head title="PPP Profile" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="PPP Profile"
                        description="Profil tersinkron otomatis ke Mikrotik (RouterOS) dan Radius."
                    />

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(pppProfileSync(), undefined, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <RefreshCw />
                            Sync dari Mikrotik
                        </Button>

                        <Button onClick={() => setCreateOpen(true)}>
                            Tambah Profile
                        </Button>
                    </div>
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

                    <div className="flex items-center gap-2">
                        Radius:
                        {radiusServer ? (
                            <Badge variant="secondary">
                                {radiusServer.name}
                            </Badge>
                        ) : (
                            <span className="text-destructive">belum ada</span>
                        )}
                    </div>

                    <p className="text-xs text-muted-foreground md:ml-auto">
                        Ubah server utama di menu Servers.
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
                        placeholder="Cari nama / pool / rate..."
                    />
                </form>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Nama</th>
                                <th className="px-4 py-3 font-medium">
                                    Local Address
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    IP Pool
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Rate Limit
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Radius
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {profiles.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada profile. Klik "Sync dari
                                        Mikrotik" untuk mengimpor yang sudah
                                        ada.
                                    </td>
                                </tr>
                            )}

                            {profiles.data.map((profile) => (
                                <tr key={profile.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {profile.name}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {profile.local_address || '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {profile.remote_address || '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {profile.rate_limit || '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                profile.radius_synced
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {profile.radius_synced
                                                ? 'tersinkron'
                                                : 'belum'}
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
                                                        setProfileToEdit(
                                                            profile,
                                                        )
                                                    }
                                                >
                                                    <UserRoundPen />
                                                    Ubah
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setProfileToDelete(
                                                            profile,
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

                {profiles.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Halaman {profiles.current_page} dari{' '}
                            {profiles.last_page} ({profiles.total} profile)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!profiles.links[0]?.url}
                                onClick={() =>
                                    profiles.links[0]?.url &&
                                    router.visit(profiles.links[0].url, {
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
                                    !profiles.links[profiles.links.length - 1]
                                        ?.url
                                }
                                onClick={() =>
                                    profiles.links[profiles.links.length - 1]
                                        ?.url &&
                                    router.visit(
                                        profiles.links[
                                            profiles.links.length - 1
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

            <PppProfileDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
            />

            {profileToEdit && (
                <PppProfileDialog
                    key={`edit-${profileToEdit.id}`}
                    open
                    onOpenChange={(open) => !open && setProfileToEdit(null)}
                    onClose={() => setProfileToEdit(null)}
                    profile={profileToEdit}
                />
            )}

            <Dialog
                open={profileToDelete !== null}
                onOpenChange={(open) => !open && setProfileToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus profile?</DialogTitle>
                        <DialogDescription>
                            Profil {profileToDelete?.name} akan dihapus dari
                            Mikrotik dan Radius. Tindakan ini tidak dapat
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
                                profileToDelete &&
                                router.delete(
                                    pppProfileDestroy(profileToDelete.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setProfileToDelete(null),
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

function PppProfileDialog({
    open,
    onOpenChange,
    onClose,
    profile,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    profile?: PppProfileRow;
}) {
    const id = profile?.id ?? 'new';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {profile ? 'Ubah Profile' : 'Tambah Profile'}
                    </DialogTitle>
                    <DialogDescription>
                        Perubahan diterapkan ke Mikrotik & Radius sekaligus.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(profile
                        ? pppProfileUpdate.form(profile.id)
                        : pppProfileStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-name`}>
                                    Nama Profile
                                </Label>

                                <Input
                                    id={`${id}-name`}
                                    name="name"
                                    required
                                    autoFocus
                                    defaultValue={profile?.name}
                                    placeholder="Contoh: PPPoE-10M"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-local`}>
                                        Local Address
                                    </Label>

                                    <Input
                                        id={`${id}-local`}
                                        name="local_address"
                                        defaultValue={
                                            profile?.local_address ?? ''
                                        }
                                        placeholder="10.10.10.1"
                                    />

                                    <InputError
                                        message={errors.local_address}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-remote`}>
                                        Remote Address / Pool
                                    </Label>

                                    <Input
                                        id={`${id}-remote`}
                                        name="remote_address"
                                        defaultValue={
                                            profile?.remote_address ?? ''
                                        }
                                        placeholder="pool-pelanggan"
                                    />

                                    <InputError
                                        message={errors.remote_address}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-dns`}>DNS</Label>

                                    <Input
                                        id={`${id}-dns`}
                                        name="dns_server"
                                        defaultValue={profile?.dns_server ?? ''}
                                        placeholder="8.8.8.8,1.1.1.1"
                                    />

                                    <InputError message={errors.dns_server} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-rate`}>
                                        Rate Limit
                                    </Label>

                                    <Input
                                        id={`${id}-rate`}
                                        name="rate_limit"
                                        defaultValue={profile?.rate_limit ?? ''}
                                        placeholder="10M/10M"
                                    />

                                    <InputError message={errors.rate_limit} />
                                </div>
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

PppProfiles.layout = {
    breadcrumbs: [
        {
            title: 'PPPoE',
            href: '/ppp/profiles',
        },
        {
            title: 'PPP Profile',
            href: '/ppp/profiles',
        },
    ],
};
