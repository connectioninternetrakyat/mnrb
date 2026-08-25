import { Form, Head, Link, router } from '@inertiajs/react';
import {
    MoreHorizontal,
    PlayCircle,
    Router,
    Trash2,
    UserRoundPen,
    View,
    Star,
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
import { Switch } from '@/components/ui/switch';
import {
    destroy as mikrotikDestroy,
    detail as mikrotikDetail,
    index as serversMikrotikIndex,
    store as mikrotikStore,
    test as mikrotikTest,
    update as mikrotikUpdate,
    defaultMethod as mikrotikDefault,
} from '@/routes/servers/mikrotik';
import type { MikrotikDetailData, MikrotikServerRow } from '@/types';

type PageProps = {
    servers: MikrotikServerRow[];
    detail: MikrotikDetailData | null;
};

export default function ServersMikrotik({ servers, detail }: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [serverToEdit, setServerToEdit] = useState<MikrotikServerRow | null>(
        null,
    );
    const [serverToDelete, setServerToDelete] =
        useState<MikrotikServerRow | null>(null);

    return (
        <>
            <Head title="Server Mikrotik" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Mikrotik"
                        description="Kelola kredensial Mikrotik API (RouterOS)."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Tambah Server
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Server
                                </th>
                                <th className="px-4 py-3 font-medium">Host</th>
                                <th className="px-4 py-3 font-medium">
                                    Username
                                </th>
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
                                        Belum ada server Mikrotik.
                                    </td>
                                </tr>
                            )}

                            {servers.map((server) => (
                                <tr key={server.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <Router className="size-4 shrink-0 text-muted-foreground" />
                                            <span className="truncate font-medium">
                                                {server.name}
                                            </span>
                                            {server.use_tls && (
                                                <Badge variant="outline">
                                                    tls
                                                </Badge>
                                            )}
                                            {server.has_password && (
                                                <Badge variant="outline">
                                                    password tersimpan
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {server.host}:{server.port}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {server.username}
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
                                            <p className="mt-1 max-w-[220px] truncate text-xs text-destructive">
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
                                                            mikrotikTest(
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
                                                        href={mikrotikDetail(
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
                                                        router.post(
                                                            mikrotikDefault(
                                                                server.id,
                                                            ),
                                                            undefined,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Star />
                                                    Jadikan Utama
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

            <MikrotikServerDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
            />

            {serverToEdit && (
                <MikrotikServerDialog
                    key={`edit-${serverToEdit.id}`}
                    open
                    onOpenChange={(open) => !open && setServerToEdit(null)}
                    onClose={() => setServerToEdit(null)}
                    server={serverToEdit}
                />
            )}

            {detail && (
                <MikrotikDetailDialog
                    key={`detail-${detail.id}`}
                    detail={detail}
                    onClose={() =>
                        router.get(serversMikrotikIndex(), undefined, {
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
                                    mikrotikDestroy(serverToDelete.id),
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

function MikrotikServerDialog({
    open,
    onOpenChange,
    onClose,
    server,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    server?: MikrotikServerRow;
}) {
    const [useTls, setUseTls] = useState(server?.use_tls ?? false);
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
                            : 'Tambahkan kredensial Mikrotik API baru.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(server
                        ? mikrotikUpdate.form(server.id)
                        : mikrotikStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="use_tls"
                                value={useTls ? '1' : '0'}
                            />

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
                                    placeholder="Contoh: Router Kantor Pusat"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid grid-cols-[1fr_auto] gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-host`}>Host</Label>

                                    <Input
                                        id={`${id}-host`}
                                        name="host"
                                        required
                                        defaultValue={server?.host}
                                        placeholder="192.168.88.1"
                                    />

                                    <InputError message={errors.host} />
                                </div>

                                <div className="grid w-28 gap-2">
                                    <Label htmlFor={`${id}-port`}>
                                        Port API
                                    </Label>

                                    <Input
                                        id={`${id}-port`}
                                        name="port"
                                        type="number"
                                        min={1}
                                        max={65535}
                                        required
                                        defaultValue={server?.port ?? 8728}
                                    />

                                    <InputError message={errors.port} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-username`}>
                                    Username
                                </Label>

                                <Input
                                    id={`${id}-username`}
                                    name="username"
                                    required
                                    defaultValue={server?.username}
                                    placeholder="admin"
                                />

                                <InputError message={errors.username} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-password`}>
                                    Password
                                </Label>

                                <Input
                                    id={`${id}-password`}
                                    name="password"
                                    type="password"
                                    autoComplete="off"
                                    placeholder={
                                        server?.has_password
                                            ? '••••••••• (tersimpan — kosongkan jika tidak diubah)'
                                            : 'Password API user'
                                    }
                                />

                                <p className="text-xs text-muted-foreground">
                                    Disimpan terenkripsi dan tidak dapat dilihat
                                    kembali.
                                </p>

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="flex items-center justify-between rounded-lg border p-3">
                                    <div className="space-y-0.5">
                                        <Label htmlFor={`${id}-tls`}>
                                            TLS / SSL
                                        </Label>

                                        <p className="text-xs text-muted-foreground">
                                            api-ssl (8729)
                                        </p>
                                    </div>

                                    <Switch
                                        id={`${id}-tls`}
                                        checked={useTls}
                                        onCheckedChange={(checked) =>
                                            setUseTls(checked)
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-timeout`}>
                                        Timeout (detik)
                                    </Label>

                                    <Input
                                        id={`${id}-timeout`}
                                        name="timeout"
                                        type="number"
                                        min={1}
                                        max={60}
                                        required
                                        defaultValue={server?.timeout ?? 5}
                                    />

                                    <InputError message={errors.timeout} />
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

function MikrotikDetailDialog({
    detail,
    onClose,
}: {
    detail: MikrotikDetailData;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Detail — {detail.name}</DialogTitle>
                    <DialogDescription>
                        Informasi sistem live dari router.
                    </DialogDescription>
                </DialogHeader>

                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                    {[
                        ['Identity', detail.identity],
                        ['Board', detail.board_name],
                        ['Model', detail.routerboard_model],
                        ['Serial', detail.serial_number],
                        ['Arsitektur', detail.architecture],
                        ['Versi RouterOS', detail.version],
                        ['Uptime', detail.uptime],
                        [
                            'CPU Load',
                            detail.cpu_load !== null &&
                            detail.cpu_load !== undefined
                                ? `${detail.cpu_load}%`
                                : null,
                        ],
                        [
                            'Memori',
                            detail.total_memory
                                ? `${Math.round(
                                      Number(detail.free_memory ?? 0) / 1048576,
                                  )} MB bebas dari ${Math.round(
                                      Number(detail.total_memory) / 1048576,
                                  )} MB`
                                : null,
                        ],
                    ].map(([label, value]) => (
                        <div
                            key={String(label)}
                            className="col-span-2 grid grid-cols-subgrid"
                        >
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="font-medium break-all">
                                {value || '-'}
                            </dd>
                        </div>
                    ))}
                </dl>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Tutup</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
