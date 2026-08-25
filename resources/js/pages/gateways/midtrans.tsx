import { Form, Head, router } from '@inertiajs/react';
import {
    CreditCard,
    MoreHorizontal,
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    destroy as midtransDestroy,
    store as midtransStore,
    update as midtransUpdate,
} from '@/routes/gateways/midtrans';
import type { MidtransAccountRow } from '@/types';

type PageProps = {
    accounts: MidtransAccountRow[];
    webhook_url: string;
    detail: null;
};

export default function GatewaysMidtrans({
    accounts,
    webhook_url: webhookUrl,
}: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [accountToEdit, setAccountToEdit] =
        useState<MidtransAccountRow | null>(null);
    const [accountToDelete, setAccountToDelete] =
        useState<MidtransAccountRow | null>(null);

    return (
        <>
            <Head title="Gateway Midtrans" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Midtrans"
                        description="Kelola kredensial gateway pembayaran Midtrans."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Tambah Akun
                    </Button>
                </div>

                <div className="space-y-2 rounded-xl border p-4">
                    <div className="flex items-center gap-2 text-sm font-medium">
                        <Webhook className="size-4 shrink-0 text-muted-foreground" />
                        Payment Notification URL
                    </div>

                    <Input readOnly value={webhookUrl} />

                    <p className="text-xs text-muted-foreground">
                        Tempel URL ini ke dashboard Midtrans &gt; Settings &gt;
                        Payment Notification URL. Otomatis mengikuti APP_URL di
                        .env.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Akun</th>
                                <th className="px-4 py-3 font-medium">
                                    Merchant ID
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Server Key
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {accounts.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada akun Midtrans.
                                    </td>
                                </tr>
                            )}

                            {accounts.map((account) => (
                                <tr key={account.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <CreditCard className="size-4 shrink-0 text-muted-foreground" />
                                            <span className="truncate font-medium">
                                                {account.name}
                                            </span>
                                            <Badge
                                                variant={
                                                    account.is_production
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {account.is_production
                                                    ? 'production'
                                                    : 'sandbox'}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {account.merchant_id || '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {account.has_server_key ? (
                                            <Badge variant="outline">
                                                tersimpan
                                            </Badge>
                                        ) : (
                                            <span>-</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                account.is_active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {account.is_active
                                                ? 'aktif'
                                                : 'nonaktif'}
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
                                                        setAccountToEdit(
                                                            account,
                                                        )
                                                    }
                                                >
                                                    <UserRoundPen />
                                                    Ubah
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setAccountToDelete(
                                                            account,
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

            <MidtransAccountDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
            />

            {accountToEdit && (
                <MidtransAccountDialog
                    key={`edit-${accountToEdit.id}`}
                    open
                    onOpenChange={(open) => !open && setAccountToEdit(null)}
                    onClose={() => setAccountToEdit(null)}
                    account={accountToEdit}
                />
            )}

            <Dialog
                open={accountToDelete !== null}
                onOpenChange={(open) => !open && setAccountToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus akun?</DialogTitle>
                        <DialogDescription>
                            Akun {accountToDelete?.name} akan dihapus permanen.
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
                                accountToDelete &&
                                router.delete(
                                    midtransDestroy(accountToDelete.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setAccountToDelete(null),
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

function MidtransAccountDialog({
    open,
    onOpenChange,
    onClose,
    account,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    account?: MidtransAccountRow;
}) {
    const [isProduction, setIsProduction] = useState(
        account?.is_production ?? false,
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {account ? 'Ubah Akun' : 'Tambah Akun'}
                    </DialogTitle>
                    <DialogDescription>
                        {account
                            ? `Perbarui kredensial akun ${account.name}.`
                            : 'Tambahkan kredensial akun Midtrans baru.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(account
                        ? midtransUpdate.form(account.id)
                        : midtransStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="scrollbar-slim max-h-[60vh] space-y-4 overflow-x-hidden overflow-y-auto"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="is_production"
                                value={isProduction ? '1' : '0'}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor={`${account?.id ?? 'new'}-name`}>
                                    Nama Akun
                                </Label>

                                <Input
                                    id={`${account?.id ?? 'new'}-name`}
                                    name="name"
                                    required
                                    autoFocus
                                    defaultValue={account?.name}
                                    placeholder="Contoh: Midtrans Utama"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${account?.id ?? 'new'}-env`}>
                                    Environment
                                </Label>

                                <Select
                                    defaultValue={
                                        isProduction ? 'production' : 'sandbox'
                                    }
                                    onValueChange={(value) =>
                                        setIsProduction(value === 'production')
                                    }
                                >
                                    <SelectTrigger
                                        id={`${account?.id ?? 'new'}-env`}
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent
                                        position="popper"
                                        avoidCollisions
                                        align="start"
                                        className="max-h-none"
                                    >
                                        <SelectItem value="sandbox">
                                            Sandbox
                                        </SelectItem>
                                        <SelectItem value="production">
                                            Production
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`${account?.id ?? 'new'}-merchant`}
                                >
                                    Merchant ID
                                </Label>

                                <Input
                                    id={`${account?.id ?? 'new'}-merchant`}
                                    name="merchant_id"
                                    defaultValue={account?.merchant_id ?? ''}
                                    placeholder="G123456789"
                                />

                                <InputError message={errors.merchant_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${account?.id ?? 'new'}-sk`}>
                                    Server Key
                                </Label>

                                <Input
                                    id={`${account?.id ?? 'new'}-sk`}
                                    name="server_key"
                                    type="password"
                                    required={!account?.has_server_key}
                                    autoComplete="off"
                                    placeholder={
                                        account?.has_server_key
                                            ? '••••••••• (tersimpan — kosongkan jika tidak diubah)'
                                            : 'SB-Mid-server-...'
                                    }
                                />

                                <p className="text-xs text-muted-foreground">
                                    Disimpan terenkripsi dan tidak dapat dilihat
                                    kembali.
                                </p>

                                <InputError message={errors.server_key} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${account?.id ?? 'new'}-ck`}>
                                    Client Key
                                </Label>

                                <Input
                                    id={`${account?.id ?? 'new'}-ck`}
                                    name="client_key"
                                    defaultValue={account?.client_key ?? ''}
                                    autoComplete="off"
                                    placeholder="SB-Mid-client-..."
                                />

                                <InputError message={errors.client_key} />
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
