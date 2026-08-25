import { Form, Head, router } from '@inertiajs/react';
import {
    KeySquare,
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
import {
    destroy as pppSecretDestroy,
    index as pppSecretIndex,
    store as pppSecretStore,
    update as pppSecretUpdate,
} from '@/routes/ppp/secret';
import type {
    PppFilters,
    PppPaginated,
    PppSecretRow,
    PppServerBadge,
} from '@/types';

type PageProps = {
    secrets: PppPaginated<PppSecretRow>;
    filters: PppFilters;
    profiles: string[];
    mikrotik_server: PppServerBadge;
};

export default function PppSecrets({ secrets, filters, profiles }: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            pppSecretIndex(),
            { search },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };
    const [secretToEdit, setSecretToEdit] = useState<PppSecretRow | null>(null);
    const [secretToDelete, setSecretToDelete] = useState<PppSecretRow | null>(
        null,
    );

    return (
        <>
            <Head title="PPP Secret" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="PPP Secret"
                        description="Pelanggan baru aktif di Radius dan dibuat disable di Mikrotik."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Tambah Secret
                    </Button>
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
                        placeholder="Cari username / profile..."
                    />
                </form>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Username
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Profile
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Mikrotik
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Radius
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {secrets.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada secret.
                                    </td>
                                </tr>
                            )}

                            {secrets.data.map((secret) => (
                                <tr key={secret.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <KeySquare className="size-4 shrink-0 text-muted-foreground" />
                                            <span className="font-medium">
                                                {secret.name}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {secret.profile}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                secret.disabled_on_mikrotik
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                        >
                                            {secret.disabled_on_mikrotik
                                                ? 'disable'
                                                : 'enable'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                secret.radius_enabled
                                                    ? 'default'
                                                    : 'destructive'
                                            }
                                        >
                                            {secret.radius_enabled
                                                ? 'aktif'
                                                : 'tidak ada'}
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
                                                        setSecretToEdit(secret)
                                                    }
                                                >
                                                    <UserRoundPen />
                                                    Ubah
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setSecretToDelete(
                                                            secret,
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

                {secrets.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Halaman {secrets.current_page} dari{' '}
                            {secrets.last_page} ({secrets.total} secret)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!secrets.links[0]?.url}
                                onClick={() =>
                                    secrets.links[0]?.url &&
                                    router.visit(secrets.links[0].url, {
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
                                    !secrets.links[secrets.links.length - 1]
                                        ?.url
                                }
                                onClick={() =>
                                    secrets.links[secrets.links.length - 1]
                                        ?.url &&
                                    router.visit(
                                        secrets.links[secrets.links.length - 1]
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

            <PppSecretDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
                profiles={profiles}
            />

            {secretToEdit && (
                <PppSecretDialog
                    key={`edit-${secretToEdit.id}`}
                    open
                    onOpenChange={(open) => !open && setSecretToEdit(null)}
                    onClose={() => setSecretToEdit(null)}
                    profiles={profiles}
                    secret={secretToEdit}
                />
            )}

            <Dialog
                open={secretToDelete !== null}
                onOpenChange={(open) => !open && setSecretToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus secret?</DialogTitle>
                        <DialogDescription>
                            Secret {secretToDelete?.name} akan dihapus dari
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
                                secretToDelete &&
                                router.delete(
                                    pppSecretDestroy(secretToDelete.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setSecretToDelete(null),
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

function PppSecretDialog({
    open,
    onOpenChange,
    onClose,
    profiles,
    secret,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    profiles: string[];
    secret?: PppSecretRow;
}) {
    const id = secret?.id ?? 'new';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {secret ? 'Ubah Secret' : 'Tambah Secret'}
                    </DialogTitle>
                    <DialogDescription>
                        {secret
                            ? `Perbarui secret ${secret.name} di kedua sistem.`
                            : 'Baru: aktif di Radius, disable di Mikrotik.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(secret
                        ? pppSecretUpdate.form(secret.id)
                        : pppSecretStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-name`}>Username</Label>

                                <Input
                                    id={`${id}-name`}
                                    name="name"
                                    required
                                    autoFocus={!secret}
                                    readOnly={!!secret}
                                    defaultValue={secret?.name}
                                    placeholder="pelanggan001"
                                />

                                <p className="text-xs text-muted-foreground">
                                    {secret
                                        ? 'Username tidak dapat diubah.'
                                        : 'Hanya huruf, angka, @ . _ -'}
                                </p>

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-pass`}>Password</Label>

                                <Input
                                    id={`${id}-pass`}
                                    name="password"
                                    type="password"
                                    autoComplete="off"
                                    required={!secret}
                                    placeholder={
                                        secret
                                            ? '••••••••• (kosongkan jika tidak diubah)'
                                            : 'Password PPPoE pelanggan'
                                    }
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-profile`}>Profile</Label>

                                <Select
                                    name="profile"
                                    defaultValue={secret?.profile ?? undefined}
                                >
                                    <SelectTrigger
                                        id={`${id}-profile`}
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pilih profile" />
                                    </SelectTrigger>
                                    <SelectContent
                                        position="popper"
                                        avoidCollisions
                                        align="start"
                                        className="max-h-none"
                                    >
                                        {profiles.map((profileName) => (
                                            <SelectItem
                                                key={profileName}
                                                value={profileName}
                                            >
                                                {profileName}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <InputError message={errors.profile} />
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
