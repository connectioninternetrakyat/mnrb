import { Form, Head, Link, router } from '@inertiajs/react';
import {
    MonitorSmartphone,
    MoreHorizontal,
    PlayCircle,
    Trash2,
    UserRoundPen,
    View,
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
    destroy as genieacsDestroy,
    detail as genieacsDetail,
    index as serversGenieacsIndex,
    store as genieacsStore,
    test as genieacsTest,
    update as genieacsUpdate,
} from '@/routes/servers/genieacs';
import type { GenieacsDetailData, GenieacsServerRow } from '@/types';

type PageProps = {
    servers: GenieacsServerRow[];
    detail: GenieacsDetailData | null;
};

export default function ServersGenieacs({ servers, detail }: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [serverToEdit, setServerToEdit] = useState<GenieacsServerRow | null>(
        null,
    );
    const [serverToDelete, setServerToDelete] =
        useState<GenieacsServerRow | null>(null);

    return (
        <>
            <Head title="Server GenieACS" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="GenieACS"
                        description="Kelola kredensial server GenieACS (NBI API)."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Tambah Server
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[680px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Server
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Base URL
                                </th>
                                <th className="px-4 py-3 font-medium">Auth</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {servers.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada server GenieACS.
                                    </td>
                                </tr>
                            )}

                            {servers.map((server) => (
                                <tr key={server.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <Wifi className="size-4 shrink-0 text-muted-foreground" />
                                            <span className="truncate font-medium">
                                                {server.name}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="max-w-[220px] truncate px-4 py-3">
                                        {server.base_url}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {server.username || '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                server.is_connected
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {server.is_connected
                                                ? 'terhubung'
                                                : 'belum terhubung'}
                                        </Badge>
                                        {server.last_error && (
                                            <p className="mt-1 max-w-[200px] truncate text-xs text-destructive">
                                                {server.last_error}
                                            </p>
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
                                                        router.post(
                                                            genieacsTest(
                                                                server.id,
                                                            ),
                                                            undefined,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <PlayCircle />
                                                    Test Connection
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={genieacsDetail(
                                                            server.id,
                                                        )}
                                                        prefetch
                                                    >
                                                        <View />
                                                        View Detail
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setServerToEdit(server)
                                                    }
                                                >
                                                    <UserRoundPen />
                                                    Ubah
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setServerToDelete(
                                                            server,
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

            <GenieacsServerDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
            />

            {serverToEdit && (
                <GenieacsServerDialog
                    key={`edit-${serverToEdit.id}`}
                    open
                    onOpenChange={(open) => !open && setServerToEdit(null)}
                    onClose={() => setServerToEdit(null)}
                    server={serverToEdit}
                />
            )}

            {detail && (
                <GenieacsDetailDialog
                    key={`detail-${detail.id}`}
                    detail={detail}
                    onClose={() =>
                        router.get(serversGenieacsIndex(), undefined, {
                            preserveScroll: true,
                        })
                    }
                />
            )}

            <Dialog
                open={serverToDelete !== null}
                onOpenChange={(open) => !open && setServerToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus server?</DialogTitle>
                        <DialogDescription>
                            Server {serverToDelete?.name} akan dihapus permanen.
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
                                serverToDelete &&
                                router.delete(
                                    genieacsDestroy(serverToDelete.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setServerToDelete(null),
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

function GenieacsServerDialog({
    open,
    onOpenChange,
    onClose,
    server,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    server?: GenieacsServerRow;
}) {
    const id = server?.id ?? 'new';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {server ? 'Ubah Server' : 'Tambah Server'}
                    </DialogTitle>
                    <DialogDescription>
                        {server
                            ? `Perbarui kredensial server ${server.name}.`
                            : 'Tambahkan kredensial GenieACS NBI baru.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(server
                        ? genieacsUpdate.form(server.id)
                        : genieacsStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-name`}>
                                    Nama Server
                                </Label>

                                <Input
                                    id={`${id}-name`}
                                    name="name"
                                    required
                                    autoFocus
                                    defaultValue={server?.name}
                                    placeholder="Contoh: GenieACS Utama"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-url`}>Base URL</Label>

                                <Input
                                    id={`${id}-url`}
                                    name="base_url"
                                    type="url"
                                    required
                                    defaultValue={server?.base_url}
                                    placeholder="http://10.10.10.6:7557"
                                />

                                <p className="text-xs text-muted-foreground">
                                    Alamat Northbound Interface (NBI) tanpa
                                    garis miring di akhir.
                                </p>

                                <InputError message={errors.base_url} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-user`}>Username</Label>

                                <Input
                                    id={`${id}-user`}
                                    name="username"
                                    defaultValue={server?.username ?? ''}
                                    autoComplete="off"
                                    placeholder="admin (kosongkan jika tanpa auth)"
                                />

                                <InputError message={errors.username} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-pass`}>Password</Label>

                                <Input
                                    id={`${id}-pass`}
                                    name="password"
                                    type="password"
                                    autoComplete="off"
                                    placeholder={
                                        server?.has_password
                                            ? '••••••••• (tersimpan — kosongkan jika tidak diubah)'
                                            : 'Password NBI'
                                    }
                                />

                                <p className="text-xs text-muted-foreground">
                                    Disimpan terenkripsi dan tidak dapat dilihat
                                    kembali.
                                </p>

                                <InputError message={errors.password} />
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

function GenieacsDetailDialog({
    detail,
    onClose,
}: {
    detail: GenieacsDetailData;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Detail — {detail.name}</DialogTitle>
                    <DialogDescription>
                        <span className="flex items-center gap-1.5">
                            <MonitorSmartphone className="size-4" />
                            {detail.total_devices} perangkat terdaftar
                        </span>
                    </DialogDescription>
                </DialogHeader>

                {detail.devices.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada perangkat terdaftar di GenieACS.
                    </p>
                ) : (
                    <div className="scrollbar-slim overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[480px] text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Product Class
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Serial Number
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Last Inform
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {detail.devices.map((device) => (
                                    <tr
                                        key={device.id ?? device.serial_number}
                                        className="border-t"
                                    >
                                        <td className="px-3 py-2">
                                            {device.product_class || '-'}
                                        </td>
                                        <td className="px-3 py-2 break-all">
                                            {device.serial_number || '-'}
                                        </td>
                                        <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                                            {device.last_inform
                                                ? new Date(
                                                      device.last_inform,
                                                  ).toLocaleString('id-ID')
                                                : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Tutup</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
