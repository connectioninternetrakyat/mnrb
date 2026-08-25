import { Form, Head, router } from '@inertiajs/react';
import {
    MessageCircle,
    MoreHorizontal,
    PlayCircle,
    QrCode,
    Trash2,
    UserRoundPen,
    Webhook,
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
import { WahaQrDialog } from '@/pages/gateways/components/waha-qr-dialog';
import {
    destroy as wahaDestroy,
    store as wahaStore,
    test as wahaTest,
    update as wahaUpdate,
} from '@/routes/gateways/waha';
import type { WahaServerRow } from '@/types';

type PageProps = {
    servers: WahaServerRow[];
    webhook_url: string;
};

export default function GatewaysWaha({
    servers,
    webhook_url: webhookUrl,
}: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [qrServer, setQrServer] = useState<WahaServerRow | null>(null);
    const [serverToEdit, setServerToEdit] = useState<WahaServerRow | null>(
        null,
    );
    const [serverToDelete, setServerToDelete] = useState<WahaServerRow | null>(
        null,
    );

    return (
        <>
            <Head title="Gateway WAHA" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="WAHA"
                        description="Kelola kredensial gateway WhatsApp (WAHA HTTP API)."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Tambah Server
                    </Button>
                </div>

                <div className="space-y-2 rounded-xl border p-4">
                    <div className="flex items-center gap-2 text-sm font-medium">
                        <Webhook className="size-4 shrink-0 text-muted-foreground" />
                        Webhook URL
                    </div>

                    <Input readOnly value={webhookUrl} />

                    <p className="text-xs text-muted-foreground">
                        Daftarkan URL ini sebagai webhook session WAHA. Event
                        diverifikasi lewat header X-Api-Key dan otomatis
                        mengikuti APP_URL di .env.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Server
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Base URL
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Session
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
                                        Belum ada server WAHA.
                                    </td>
                                </tr>
                            )}

                            {servers.map((server) => (
                                <tr key={server.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <MessageCircle className="size-4 shrink-0 text-muted-foreground" />
                                            <span className="truncate font-medium">
                                                {server.name}
                                            </span>
                                            {server.has_api_key && (
                                                <Badge variant="outline">
                                                    api key tersimpan
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="max-w-[220px] truncate px-4 py-3">
                                        {server.base_url}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {server.session}
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
                                                            wahaTest(server.id),
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
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setQrServer(server)
                                                    }
                                                >
                                                    <QrCode />
                                                    Scan QR
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

            <WahaServerDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
            />

            {serverToEdit && (
                <WahaServerDialog
                    key={`edit-${serverToEdit.id}`}
                    open
                    onOpenChange={(open) => !open && setServerToEdit(null)}
                    onClose={() => setServerToEdit(null)}
                    server={serverToEdit}
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
                                router.delete(wahaDestroy(serverToDelete.id), {
                                    preserveScroll: true,
                                    onSuccess: () => setServerToDelete(null),
                                })
                            }
                        >
                            Hapus
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {qrServer && (
                <WahaQrDialog
                    server={qrServer}
                    onClose={() => setQrServer(null)}
                />
            )}
        </>
    );
}

function WahaServerDialog({
    open,
    onOpenChange,
    onClose,
    server,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    server?: WahaServerRow;
}) {
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
                            : 'Tambahkan kredensial server WAHA baru.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(server
                        ? wahaUpdate.form(server.id)
                        : wahaStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${server?.id ?? 'new'}-name`}>
                                    Nama Server
                                </Label>

                                <Input
                                    id={`${server?.id ?? 'new'}-name`}
                                    name="name"
                                    required
                                    autoFocus
                                    defaultValue={server?.name}
                                    placeholder="Contoh: WAHA Utama"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${server?.id ?? 'new'}-url`}>
                                    Base URL
                                </Label>

                                <Input
                                    id={`${server?.id ?? 'new'}-url`}
                                    name="base_url"
                                    type="url"
                                    required
                                    defaultValue={server?.base_url}
                                    placeholder="https://waha.domain-anda.com"
                                />

                                <InputError message={errors.base_url} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${server?.id ?? 'new'}-key`}>
                                    API Key
                                </Label>

                                <Input
                                    id={`${server?.id ?? 'new'}-key`}
                                    name="api_key"
                                    type="password"
                                    required={!server?.has_api_key}
                                    autoComplete="off"
                                    placeholder={
                                        server?.has_api_key
                                            ? '••••••••• (tersimpan — kosongkan jika tidak diubah)'
                                            : 'API key dari server WAHA'
                                    }
                                />

                                <p className="text-xs text-muted-foreground">
                                    Disimpan terenkripsi dan tidak dapat dilihat
                                    kembali.
                                </p>

                                <InputError message={errors.api_key} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${server?.id ?? 'new'}-sess`}>
                                    Session
                                </Label>

                                <Input
                                    id={`${server?.id ?? 'new'}-sess`}
                                    name="session"
                                    required
                                    defaultValue={server?.session ?? 'default'}
                                    placeholder="default"
                                />

                                <InputError message={errors.session} />
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
