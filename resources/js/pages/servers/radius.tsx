import { Form, Head, router } from '@inertiajs/react';
import {
    Database,
    MoreHorizontal,
    PlayCircle,
    ShieldCheck,
    Trash2,
    UserRoundPen,
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    destroy as radiusDestroy,
    store as radiusStore,
    testDatabase as radiusTestDatabase,
    testRadius as radiusTestRadius,
    update as radiusUpdate,
    defaultMethod as radiusDefault,
} from '@/routes/servers/radius';
import type { RadiusServerRow } from '@/types';

type PageProps = {
    servers: RadiusServerRow[];
    drivers: Array<{ value: string; label: string }>;
};

export default function ServersRadius({ servers, drivers }: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [serverToEdit, setServerToEdit] = useState<RadiusServerRow | null>(
        null,
    );
    const [serverToDelete, setServerToDelete] =
        useState<RadiusServerRow | null>(null);
    const [serverToTest, setServerToTest] = useState<RadiusServerRow | null>(
        null,
    );

    return (
        <>
            <Head title="Server Radius" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Radius"
                        description="Kelola kredensial server FreeRADIUS dan database radius (MySQL / PostgreSQL)."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Tambah Server
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Server
                                </th>
                                <th className="px-4 py-3 font-medium">Host</th>
                                <th className="px-4 py-3 font-medium">
                                    Database
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
                                        Belum ada server Radius.
                                    </td>
                                </tr>
                            )}

                            {servers.map((server) => (
                                <tr key={server.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <ShieldCheck className="size-4 shrink-0 text-muted-foreground" />
                                            <span className="truncate font-medium">
                                                {server.name}
                                            </span>
                                            {server.has_shared_secret && (
                                                <Badge variant="outline">
                                                    secret tersimpan
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {server.server_host}:{server.auth_port}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <span className="flex items-center gap-1.5">
                                            <Database className="size-3.5 text-muted-foreground" />
                                            {server.db_database || '-'}{' '}
                                            <Badge variant="secondary">
                                                {server.db_driver}
                                            </Badge>
                                        </span>
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
                                                        setServerToTest(server)
                                                    }
                                                >
                                                    <PlayCircle />
                                                    Test Connection
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        router.post(
                                                            radiusDefault(
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

            <RadiusServerDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
                drivers={drivers}
            />

            {serverToEdit && (
                <RadiusServerDialog
                    key={`edit-${serverToEdit.id}`}
                    open
                    onOpenChange={(open) => !open && setServerToEdit(null)}
                    onClose={() => setServerToEdit(null)}
                    drivers={drivers}
                    server={serverToEdit}
                />
            )}

            {serverToTest && (
                <RadiusTestDialog
                    key={`test-${serverToTest.id}`}
                    server={serverToTest}
                    onClose={() => setServerToTest(null)}
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
                                    radiusDestroy(serverToDelete.id),
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

function RadiusServerDialog({
    open,
    onOpenChange,
    onClose,
    drivers,
    server,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    drivers: Array<{ value: string; label: string }>;
    server?: RadiusServerRow;
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
                            : 'Tambahkan server FreeRADIUS dan database radius baru.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(server
                        ? radiusUpdate.form(server.id)
                        : radiusStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="scrollbar-slim max-h-[60vh] space-y-6 overflow-x-hidden overflow-y-auto pr-1"
                >
                    {({ processing, errors }) => (
                        <>
                            <RadiusDriverSelectBridge
                                idBase={String(id)}
                                drivers={drivers}
                                initialDriver={server?.db_driver ?? 'mysql'}
                            />

                            <div className="space-y-4">
                                <p className="text-sm font-medium">
                                    Server FreeRADIUS
                                </p>

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
                                        placeholder="Contoh: RADIUS Utama"
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid grid-cols-[1fr_auto] gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor={`${id}-shost`}>
                                            Host
                                        </Label>

                                        <Input
                                            id={`${id}-shost`}
                                            name="server_host"
                                            required
                                            defaultValue={server?.server_host}
                                            placeholder="10.10.10.5"
                                        />

                                        <InputError
                                            message={errors.server_host}
                                        />
                                    </div>

                                    <div className="grid w-28 gap-2">
                                        <Label htmlFor={`${id}-aport`}>
                                            Port Auth
                                        </Label>

                                        <Input
                                            id={`${id}-aport`}
                                            name="auth_port"
                                            type="number"
                                            min={1}
                                            max={65535}
                                            required
                                            defaultValue={
                                                server?.auth_port ?? 1812
                                            }
                                        />

                                        <InputError
                                            message={errors.auth_port}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-secret`}>
                                        Shared Secret
                                    </Label>

                                    <Input
                                        id={`${id}-secret`}
                                        name="shared_secret"
                                        type="password"
                                        autoComplete="off"
                                        required={!server?.has_shared_secret}
                                        placeholder={
                                            server?.has_shared_secret
                                                ? '••••••••• (tersimpan — kosongkan jika tidak diubah)'
                                                : 'Secret dari clients.conf'
                                        }
                                    />

                                    <InputError
                                        message={errors.shared_secret}
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

                            <div className="space-y-4 border-t pt-4">
                                <p className="flex items-center gap-2 text-sm font-medium">
                                    <Database className="size-4 shrink-0 text-muted-foreground" />
                                    Database Radius
                                </p>

                                <div className="grid grid-cols-[1fr_auto] gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor={`${id}-dbhost`}>
                                            Host Database
                                        </Label>

                                        <Input
                                            id={`${id}-dbhost`}
                                            name="db_host"
                                            defaultValue={server?.db_host ?? ''}
                                            placeholder="127.0.0.1"
                                        />

                                        <InputError message={errors.db_host} />
                                    </div>

                                    <div className="grid w-32 gap-2">
                                        <Label htmlFor={`${id}-dbport`}>
                                            Port
                                        </Label>

                                        <Input
                                            id={`${id}-dbport`}
                                            name="db_port"
                                            type="number"
                                            min={1}
                                            max={65535}
                                            defaultValue={
                                                server?.db_port ?? undefined
                                            }
                                            placeholder="3306 / 5432"
                                        />

                                        <InputError message={errors.db_port} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-dbdb`}>
                                        Nama Database
                                    </Label>

                                    <Input
                                        id={`${id}-dbdb`}
                                        name="db_database"
                                        defaultValue={server?.db_database ?? ''}
                                        placeholder="radius"
                                    />

                                    <InputError message={errors.db_database} />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor={`${id}-dbuser`}>
                                            Username DB
                                        </Label>

                                        <Input
                                            id={`${id}-dbuser`}
                                            name="db_username"
                                            defaultValue={
                                                server?.db_username ?? ''
                                            }
                                            placeholder="radius"
                                        />

                                        <InputError
                                            message={errors.db_username}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor={`${id}-dbpass`}>
                                            Password DB
                                        </Label>

                                        <Input
                                            id={`${id}-dbpass`}
                                            name="db_password"
                                            type="password"
                                            autoComplete="off"
                                            placeholder={
                                                server?.has_db_password
                                                    ? '••••••••• (tersimpan)'
                                                    : 'Password database'
                                            }
                                        />

                                        <InputError
                                            message={errors.db_password}
                                        />
                                    </div>
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

function RadiusDriverSelectBridge({
    idBase,
    drivers,
    initialDriver,
}: {
    idBase: string;
    drivers: Array<{ value: string; label: string }>;
    initialDriver: string;
}) {
    const [driver, setDriver] = useState(initialDriver);

    return (
        <>
            <input type="hidden" name="db_driver" value={driver} />

            <div className="grid gap-2">
                <Label htmlFor={`${idBase}-driver`}>Driver</Label>

                <Select defaultValue={initialDriver} onValueChange={setDriver}>
                    <SelectTrigger id={`${idBase}-driver`} className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent
                        position="popper"
                        avoidCollisions
                        align="start"
                        className="max-h-none"
                    >
                        {drivers.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <p className="text-xs text-muted-foreground">
                    Mendukung MySQL dan PostgreSQL.
                </p>
            </div>
        </>
    );
}

function RadiusTestDialog({
    server,
    onClose,
}: {
    server: RadiusServerRow;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Test — {server.name}</DialogTitle>
                    <DialogDescription>
                        Pilih jenis tes yang ingin dijalankan.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const data = new FormData(e.currentTarget);

                        router.post(
                            radiusTestRadius(server.id),
                            {
                                test_username: String(
                                    data.get('test_username') ?? '',
                                ),
                                test_password: String(
                                    data.get('test_password') ?? '',
                                ),
                            },
                            { preserveScroll: true, onSuccess: onClose },
                        );
                    }}
                    className="space-y-3"
                >
                    <p className="text-xs text-muted-foreground">
                        Kirim Access-Request nyata ke FreeRADIUS dengan
                        kredensial uji.
                    </p>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor={`test-user-${server.id}`}>
                                Username Uji
                            </Label>

                            <Input
                                id={`test-user-${server.id}`}
                                name="test_username"
                                placeholder="testuser"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`test-pass-${server.id}`}>
                                Password Uji
                            </Label>

                            <Input
                                id={`test-pass-${server.id}`}
                                name="test_password"
                                type="password"
                                placeholder="testpass"
                            />
                        </div>
                    </div>

                    <Button variant="outline" type="submit" className="w-full">
                        Test FreeRADIUS
                    </Button>
                </form>

                <div className="border-t pt-3">
                    <Button
                        variant="outline"
                        className="w-full"
                        onClick={() =>
                            router.post(
                                radiusTestDatabase(server.id),
                                undefined,
                                { preserveScroll: true, onSuccess: onClose },
                            )
                        }
                    >
                        Test Koneksi Database
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
