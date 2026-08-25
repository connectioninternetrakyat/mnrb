import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    MoreHorizontal,
    ShieldCheck,
    Trash2,
    UserRoundPen,
} from 'lucide-react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
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
import { Textarea } from '@/components/ui/textarea';
import { index as rolesIndex } from '@/routes/roles';
import type { RoleRow } from '@/types';

type PageProps = {
    roles: RoleRow[];
};

export default function RolesIndex() {
    const { roles } = usePage<PageProps>().props;
    const [roleToDelete, setRoleToDelete] = useState<RoleRow | null>(null);
    const [roleToEdit, setRoleToEdit] = useState<RoleRow | null>(null);
    const [createOpen, setCreateOpen] = useState(false);

    return (
        <>
            <Head title="Manajemen Role" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Role"
                        description="Kelola role yang dapat ditugaskan ke pengguna."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Tambah Role
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Role</th>
                                <th className="px-4 py-3 font-medium">
                                    Pengguna
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Akses Menu
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {roles.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Tidak ada role ditemukan.
                                    </td>
                                </tr>
                            )}

                            {roles.map((role) => (
                                <tr key={role.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <ShieldCheck className="size-4 shrink-0 text-muted-foreground" />
                                                <span className="truncate font-medium">
                                                    {role.name}
                                                </span>
                                                {role.locked && (
                                                    <Badge variant="outline">
                                                        bawaan
                                                    </Badge>
                                                )}
                                            </div>
                                            {role.description && (
                                                <p className="mt-0.5 line-clamp-2 pl-6 text-xs text-muted-foreground">
                                                    {role.description}
                                                </p>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {role.users_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {role.menus.length > 0 ? (
                                                role.menus.map((menu) => (
                                                    <Badge
                                                        key={menu}
                                                        variant="secondary"
                                                    >
                                                        {menu}
                                                    </Badge>
                                                ))
                                            ) : (
                                                <span>-</span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {!role.locked && (
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
                                                            setRoleToEdit(role)
                                                        }
                                                    >
                                                        <UserRoundPen />
                                                        Ubah
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setRoleToDelete(
                                                                role,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                        Hapus
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                open={roleToDelete !== null}
                onOpenChange={(open) => !open && setRoleToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus role?</DialogTitle>
                        <DialogDescription>
                            Role {roleToDelete?.name} akan dihapus permanen.
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
                                roleToDelete &&
                                router.delete(
                                    RoleController.destroy.url(roleToDelete.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setRoleToDelete(null),
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
                <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Tambah Role</DialogTitle>
                        <DialogDescription>
                            Buat role baru yang dapat ditugaskan ke pengguna.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        {...RoleController.store.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setCreateOpen(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="create-role-name">
                                        Nama Role
                                    </Label>

                                    <Input
                                        id="create-role-name"
                                        name="name"
                                        required
                                        autoFocus
                                        placeholder="Contoh: supervisor lapangan"
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-role-description">
                                        Deskripsi
                                    </Label>

                                    <Textarea
                                        id="create-role-description"
                                        name="description"
                                        rows={3}
                                        placeholder="Jelaskan tanggung jawab role ini..."
                                    />

                                    <InputError message={errors.description} />
                                </div>

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
                open={roleToEdit !== null}
                onOpenChange={(open) => !open && setRoleToEdit(null)}
            >
                <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Ubah Role</DialogTitle>
                        <DialogDescription>
                            Perbarui nama dan deskripsi role {roleToEdit?.name}.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        {...RoleController.update.form(roleToEdit?.id ?? 0)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setRoleToEdit(null)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-role-name">
                                        Nama Role
                                    </Label>

                                    <Input
                                        id="edit-role-name"
                                        name="name"
                                        required
                                        autoFocus
                                        defaultValue={roleToEdit?.name}
                                        placeholder="Contoh: supervisor lapangan"
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="edit-role-description">
                                        Deskripsi
                                    </Label>

                                    <Textarea
                                        id="edit-role-description"
                                        name="description"
                                        rows={3}
                                        defaultValue={
                                            roleToEdit?.description ?? ''
                                        }
                                        placeholder="Jelaskan tanggung jawab role ini..."
                                    />

                                    <InputError message={errors.description} />
                                </div>

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

RolesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Role',
            href: rolesIndex(),
        },
    ],
};
