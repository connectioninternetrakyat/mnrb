import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    ArrowDownToLine,
    Ban,
    Eye,
    MoreHorizontal,
    PlayCircle,
    Search,
    TrendingUp,
    Trash2,
    UserRoundPlus,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    activate as customerActivate,
    billsDownload as customerBillsDownload,
    destroy as customerDestroy,
    index as customerIndex,
    show as customerShow,
    store as customerStore,
    suspend as customerSuspend,
    update as customerUpdate,
    upgrade as customerUpgrade,
} from '@/routes/billing/customer';
import type { Auth } from '@/types';

type CustomerRow = {
    id: number;
    name: string;
    email: string | null;
    whatsapp: string;
    address: string;
    area_code_id: number;
    area_code: string | null;
    area_name: string | null;
    package_id: number;
    package_name: string | null;
    package_price: number | null;
    status: 'aktif' | 'ditangguhkan';
    notes: string | null;
    secret_username: string | null;
    secret_profile: string | null;
    has_secret: boolean;
};

type Option = { id: number; code?: string; name: string };

type PackageOption = {
    id: number;
    name: string;
    price: number;
    profile_name: string | null;
};

type PageProps = {
    customers: {
        data: CustomerRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    areas: Option[];
    packages: PackageOption[];
    filters: { search?: string | null };
    auth: Auth;
};

const rupiah = (value: number) =>
    `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;

export default function BillingCustomers({
    customers,
    areas,
    packages,
    filters,
}: PageProps) {
    const { auth } = usePage<PageProps>().props;

    const canUpdate = auth.user.permissions.includes('billing.update');
    const canDelete = auth.user.permissions.includes('billing.delete');

    const [search, setSearch] = useState(filters.search ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<CustomerRow | null>(null);
    const [upgradeTarget, setUpgradeTarget] = useState<CustomerRow | null>(
        null,
    );
    const [deleteTarget, setDeleteTarget] = useState<CustomerRow | null>(null);

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            customerIndex(),
            { search },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Customer" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Customer"
                        description="Kelola pelanggan billing beserta akun PPPoE dan paketnya."
                    />

                    {canUpdate && (
                        <Button onClick={() => setCreateOpen(true)}>
                            <UserRoundPlus />
                            Tambah Customer
                        </Button>
                    )}
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
                        placeholder="Cari nama / wa / username..."
                    />
                </form>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[900px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Nama</th>
                                <th className="px-4 py-3 font-medium">Area</th>
                                <th className="px-4 py-3 font-medium">Paket</th>
                                <th className="px-4 py-3 font-medium">
                                    Akun PPPoE
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {customers.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada customer.
                                    </td>
                                </tr>
                            )}

                            {customers.data.map((customer) => (
                                <tr key={customer.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {customer.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {customer.whatsapp}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {customer.area_code ? (
                                            <Badge variant="outline">
                                                {customer.area_code}
                                            </Badge>
                                        ) : (
                                            '-'
                                        )}
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {customer.area_name}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <p>{customer.package_name || '-'}</p>
                                        {customer.package_price !== null && (
                                            <p className="text-xs text-muted-foreground">
                                                {rupiah(customer.package_price)}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs whitespace-nowrap">
                                        {customer.secret_username ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                customer.status === 'aktif'
                                                    ? 'default'
                                                    : 'destructive'
                                            }
                                        >
                                            {customer.status === 'aktif'
                                                ? 'Aktif'
                                                : 'Ditangguhkan'}
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
                                                        router.visit(
                                                            customerShow(
                                                                customer.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Eye />
                                                    Detail
                                                </DropdownMenuItem>

                                                {canUpdate &&
                                                    customer.has_secret && (
                                                        <>
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    setUpgradeTarget(
                                                                        customer,
                                                                    )
                                                                }
                                                            >
                                                                <TrendingUp />
                                                                Upgrade Paket
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    router.post(
                                                                        customer.status ===
                                                                            'aktif'
                                                                            ? customerSuspend(
                                                                                  customer.id,
                                                                              )
                                                                            : customerActivate(
                                                                                  customer.id,
                                                                              ),
                                                                        undefined,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                {customer.status ===
                                                                'aktif' ? (
                                                                    <Ban />
                                                                ) : (
                                                                    <PlayCircle />
                                                                )}
                                                                {customer.status ===
                                                                'aktif'
                                                                    ? 'Tangguhkan'
                                                                    : 'Aktifkan'}
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}

                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        window.open(
                                                            customerBillsDownload(
                                                                customer.id,
                                                            ).url,
                                                            '_blank',
                                                        )
                                                    }
                                                >
                                                    <ArrowDownToLine />
                                                    Unduh Tagihan (CSV)
                                                </DropdownMenuItem>

                                                {canDelete && (
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setDeleteTarget(
                                                                customer,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                        Hapus
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {customers.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Halaman {customers.current_page} dari{' '}
                            {customers.last_page} ({customers.total} customer)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!customers.links[0]?.url}
                                onClick={() =>
                                    customers.links[0]?.url &&
                                    router.visit(customers.links[0].url, {
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
                                    !customers.links[customers.links.length - 1]
                                        ?.url
                                }
                                onClick={() =>
                                    customers.links[customers.links.length - 1]
                                        ?.url &&
                                    router.visit(
                                        customers.links[
                                            customers.links.length - 1
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

            <CustomerFormDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
                areas={areas}
                packages={packages}
            />

            {editTarget && (
                <CustomerFormDialog
                    key={`edit-${editTarget.id}`}
                    open
                    onOpenChange={(open) => !open && setEditTarget(null)}
                    onClose={() => setEditTarget(null)}
                    areas={areas}
                    packages={packages}
                    customer={editTarget}
                />
            )}

            {upgradeTarget && (
                <UpgradeDialog
                    key={`upgrade-${upgradeTarget.id}`}
                    open
                    onOpenChange={(open) => !open && setUpgradeTarget(null)}
                    packages={packages}
                    customer={upgradeTarget}
                />
            )}

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus customer?</DialogTitle>
                        <DialogDescription>
                            Data customer {deleteTarget?.name} akan dihapus
                            permanen. Akun PPPoE aktif tidak boleh masih
                            terhubung.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Batal</Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            onClick={() =>
                                deleteTarget &&
                                router.delete(
                                    customerDestroy(deleteTarget.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setDeleteTarget(null),
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

function CustomerFormDialog({
    open,
    onOpenChange,
    onClose,
    areas,
    packages,
    customer,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    areas: Option[];
    packages: PackageOption[];
    customer?: CustomerRow;
}) {
    const id = customer?.id ?? 'new';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {customer ? 'Ubah Customer' : 'Tambah Customer'}
                    </DialogTitle>
                    {!customer && (
                        <DialogDescription>
                            Centang provisioning untuk langsung membuat akun
                            PPPoE di Mikrotik &amp; Radius.
                        </DialogDescription>
                    )}
                </DialogHeader>

                <Form
                    {...(customer
                        ? customerUpdate.form(customer.id)
                        : customerStore.form())}
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
                                    defaultValue={customer?.name}
                                    placeholder="Nama pelanggan"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-email`}>Email</Label>

                                    <Input
                                        id={`${id}-email`}
                                        name="email"
                                        type="email"
                                        defaultValue={customer?.email ?? ''}
                                    />

                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-wa`}>
                                        No WhatsApp
                                    </Label>

                                    <Input
                                        id={`${id}-wa`}
                                        name="whatsapp"
                                        required
                                        defaultValue={customer?.whatsapp}
                                        placeholder="08..."
                                    />

                                    <InputError message={errors.whatsapp} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-address`}>Alamat</Label>

                                <Textarea
                                    id={`${id}-address`}
                                    name="address"
                                    rows={2}
                                    required
                                    defaultValue={customer?.address}
                                />

                                <InputError message={errors.address} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label>Area</Label>

                                    <Select
                                        name="area_code_id"
                                        defaultValue={
                                            customer
                                                ? String(customer.area_code_id)
                                                : undefined
                                        }
                                        required
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Pilih area" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {areas.map((area) => (
                                                <SelectItem
                                                    key={area.id}
                                                    value={String(area.id)}
                                                >
                                                    {area.code} — {area.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <InputError message={errors.area_code_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Paket</Label>

                                    <Select
                                        name="package_id"
                                        defaultValue={
                                            customer
                                                ? String(customer.package_id)
                                                : undefined
                                        }
                                        required
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Pilih paket" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {packages.map((item) => (
                                                <SelectItem
                                                    key={item.id}
                                                    value={String(item.id)}
                                                >
                                                    {item.name} ·{' '}
                                                    {rupiah(item.price)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <InputError message={errors.package_id} />
                                </div>
                            </div>

                            {!customer && (
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox name="provision" defaultChecked />
                                    Buat akun PPPoE otomatis (Mikrotik &amp;
                                    Radius)
                                </label>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-notes`}>
                                    Catatan{' '}
                                    <span className="text-muted-foreground">
                                        (opsional)
                                    </span>
                                </Label>

                                <Textarea
                                    id={`${id}-notes`}
                                    name="notes"
                                    rows={2}
                                    defaultValue={customer?.notes ?? ''}
                                />

                                <InputError message={errors.notes} />
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

function UpgradeDialog({
    open,
    onOpenChange,
    packages,
    customer,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    packages: PackageOption[];
    customer: CustomerRow;
}) {
    const [packageId, setPackageId] = useState(String(customer.package_id));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ubah paket {customer.name}</DialogTitle>
                    <DialogDescription>
                        Profil PPP di Mikrotik &amp; Radius akan dipindahkan ke
                        profil paket baru secara otomatis. Tagihan bulan
                        berjalan mengikuti harga paket lama.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...customerUpgrade.form(customer.id)}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="package_id"
                                value={packageId}
                            />

                            <div className="grid gap-2">
                                <Label>Paket Baru</Label>

                                <Select
                                    value={packageId}
                                    onValueChange={setPackageId}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih paket baru" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {packages.map((item) => (
                                            <SelectItem
                                                key={item.id}
                                                value={String(item.id)}
                                            >
                                                {item.name} ·{' '}
                                                {rupiah(item.price)} ·{' '}
                                                {item.profile_name ?? '-'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <InputError message={errors.package_id} />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="outline">Batal</Button>
                                </DialogClose>

                                <Button
                                    disabled={
                                        processing ||
                                        packageId ===
                                            String(customer.package_id)
                                    }
                                >
                                    <TrendingUp />
                                    Terapkan Paket
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
