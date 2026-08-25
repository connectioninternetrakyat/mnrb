import { Form, Head, router, usePage } from '@inertiajs/react';
import { MoreHorizontal, Search, Trash2, UserRoundPen } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { UserFields } from '@/pages/users/components/user-fields';
import { index as usersIndex } from '@/routes/users';
import type { RoleOption, UserFilters, UserRow } from '@/types';

type PageProps = {
    users: {
        data: UserRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: UserFilters;
    roles: RoleOption[];
    assignableRoles: RoleOption[];
};

export default function UsersIndex() {
    const { users, filters, roles, assignableRoles } =
        usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [userToDelete, setUserToDelete] = useState<UserRow | null>(null);
    const [userToEdit, setUserToEdit] = useState<UserRow | null>(null);
    const [createOpen, setCreateOpen] = useState(false);

    const applyFilter = (params: Record<string, string>) => {
        router.get(usersIndex(), params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();
        applyFilter({ search, role: filters.role ?? '' });
    };

    const handleRoleChange = (value: string) => {
        applyFilter({ search, role: value === 'all' ? '' : value });
    };

    const previousPageLink = users.links[0]?.url ?? null;
    const nextPageLink = users.links[users.links.length - 1]?.url ?? null;

    return (
        <>
            <Head title="Manajemen Pengguna" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Pengguna"
                        description="Kelola akun pengguna dan peran mereka di platform."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Tambah Pengguna
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <form
                        onSubmit={submitSearch}
                        className="relative w-full sm:w-auto"
                    >
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari nama atau email..."
                            className="w-full pl-8 sm:w-64"
                        />
                    </form>

                    <Select
                        value={filters.role || 'all'}
                        onValueChange={handleRoleChange}
                    >
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="Semua peran" />
                        </SelectTrigger>
                        <SelectContent
                            position="popper"
                            avoidCollisions
                            align="start"
                            className="max-h-none"
                        >
                            <SelectItem value="all">Semua peran</SelectItem>
                            {roles.map((role) => (
                                <SelectItem key={role.value} value={role.value}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Pengguna
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    WhatsApp
                                </th>
                                <th className="px-4 py-3 font-medium">Peran</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Terdaftar
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Tidak ada pengguna ditemukan.
                                    </td>
                                </tr>
                            )}

                            {users.data.map((user) => (
                                <tr key={user.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            {user.avatar_url ? (
                                                <img
                                                    src={user.avatar_url}
                                                    alt={user.name}
                                                    className="size-9 rounded-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex size-9 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                                    {user.name
                                                        .charAt(0)
                                                        .toUpperCase()}
                                                </div>
                                            )}
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {user.name}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {user.email}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {user.whatsapp ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {user.roles.length > 0 ? (
                                                user.roles.map((role) => (
                                                    <Badge
                                                        key={role}
                                                        variant="secondary"
                                                    >
                                                        {role}
                                                    </Badge>
                                                ))
                                            ) : (
                                                <span>-</span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {user.email_verified_at ? (
                                            <Badge>verified</Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                unverified
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {new Date(
                                            user.created_at,
                                        ).toLocaleDateString('id-ID', {
                                            day: 'numeric',
                                            month: 'short',
                                            year: 'numeric',
                                        })}
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
                                                        setUserToEdit(user)
                                                    }
                                                >
                                                    <UserRoundPen />
                                                    Ubah
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setUserToDelete(user)
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

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Halaman {users.current_page} dari {users.last_page}{' '}
                            ({users.total} pengguna)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!previousPageLink}
                                onClick={() =>
                                    previousPageLink &&
                                    router.visit(previousPageLink, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Sebelumnya
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!nextPageLink}
                                onClick={() =>
                                    nextPageLink &&
                                    router.visit(nextPageLink, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <Dialog
                open={userToDelete !== null}
                onOpenChange={(open) => !open && setUserToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus pengguna?</DialogTitle>
                        <DialogDescription>
                            Akun {userToDelete?.name} ({userToDelete?.email})
                            akan dihapus permanen. Tindakan ini tidak dapat
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
                                userToDelete &&
                                router.delete(
                                    UserController.destroy.url(userToDelete.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setUserToDelete(null),
                                    },
                                )
                            }
                        >
                            Hapus
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Tambah Pengguna</DialogTitle>
                        <DialogDescription>
                            Buat akun pengguna baru dan tentukan perannya.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        {...UserController.store.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setCreateOpen(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <UserFields
                                    roles={assignableRoles}
                                    errors={errors}
                                />

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Batal
                                        </Button>
                                    </DialogClose>

                                    <Button disabled={processing}>
                                        Simpan
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={userToEdit !== null}
                onOpenChange={(open) => !open && setUserToEdit(null)}
            >
                <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Ubah Pengguna</DialogTitle>
                        <DialogDescription>
                            Perbarui informasi akun {userToEdit?.name}.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        {...UserController.update.form(userToEdit?.id ?? 0)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setUserToEdit(null)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                {userToEdit && (
                                    <UserFields
                                        roles={assignableRoles}
                                        errors={errors}
                                        targetUser={{
                                            id: userToEdit.id,
                                            name: userToEdit.name,
                                            email: userToEdit.email,
                                            avatar_url: userToEdit.avatar_url,
                                            whatsapp: userToEdit.whatsapp,
                                            roles: userToEdit.roles,
                                        }}
                                    />
                                )}

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Batal
                                        </Button>
                                    </DialogClose>

                                    <Button disabled={processing}>
                                        Simpan
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Pengguna',
            href: usersIndex(),
        },
    ],
};
