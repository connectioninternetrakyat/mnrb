import { Form, Head, router } from '@inertiajs/react';
import { Map, MoreHorizontal, Trash2, UserRoundPen } from 'lucide-react';
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
import { Textarea } from '@/components/ui/textarea';
import {
    destroy as areaCodeDestroy,
    store as areaCodeStore,
    update as areaCodeUpdate,
} from '@/routes/ftth/area-codes';
import type { AreaCodeRow } from '@/types';

type PageProps = {
    areas: AreaCodeRow[];
};

export default function FtthAreaCodes({ areas }: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [areaToEdit, setAreaToEdit] = useState<AreaCodeRow | null>(null);
    const [areaToDelete, setAreaToDelete] = useState<AreaCodeRow | null>(null);

    return (
        <>
            <Head title="Kode Area" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Kode Area"
                        description="Kelola kode area untuk pengelompokan ODP/ODC."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        Tambah Kode Area
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Kode</th>
                                <th className="px-4 py-3 font-medium">Nama</th>
                                <th className="px-4 py-3 font-medium">
                                    Wilayah
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    ODP/ODC
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Keterangan
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {areas.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada kode area.
                                    </td>
                                </tr>
                            )}

                            {areas.map((area) => (
                                <tr key={area.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <Map className="size-4 shrink-0 text-muted-foreground" />
                                            <Badge variant="secondary">
                                                {area.code}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {area.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {area.region || '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {area.devices_count ?? 0}
                                    </td>
                                    <td className="max-w-[240px] truncate px-4 py-3 text-muted-foreground">
                                        {area.description || '-'}
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
                                                        setAreaToEdit(area)
                                                    }
                                                >
                                                    <UserRoundPen />
                                                    Ubah
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setAreaToDelete(area)
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

            <AreaCodeDialog
                key="create"
                open={createOpen}
                onOpenChange={setCreateOpen}
                onClose={() => setCreateOpen(false)}
            />

            {areaToEdit && (
                <AreaCodeDialog
                    key={`edit-${areaToEdit.id}`}
                    open
                    onOpenChange={(open) => !open && setAreaToEdit(null)}
                    onClose={() => setAreaToEdit(null)}
                    area={areaToEdit}
                />
            )}

            <Dialog
                open={areaToDelete !== null}
                onOpenChange={(open) => !open && setAreaToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus kode area?</DialogTitle>
                        <DialogDescription>
                            Kode area {areaToDelete?.code} ({areaToDelete?.name}
                            ) akan dihapus permanen. Tindakan ini tidak dapat
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
                                areaToDelete &&
                                router.delete(
                                    areaCodeDestroy(areaToDelete.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setAreaToDelete(null),
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

function AreaCodeDialog({
    open,
    onOpenChange,
    onClose,
    area,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    area?: AreaCodeRow;
}) {
    const id = area?.id ?? 'new';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="scrollbar-slim max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {area ? 'Ubah Kode Area' : 'Tambah Kode Area'}
                    </DialogTitle>
                    <DialogDescription>
                        {area
                            ? `Perbarui kode area ${area.code}.`
                            : 'Tambahkan kode area baru.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(area ? areaCodeUpdate(area.id) : areaCodeStore.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-code`}>Kode</Label>

                                <Input
                                    id={`${id}-code`}
                                    name="code"
                                    required
                                    autoFocus
                                    defaultValue={area?.code}
                                    placeholder="Contoh: TCL01"
                                />

                                <InputError message={errors.code} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-name`}>Nama</Label>

                                <Input
                                    id={`${id}-name`}
                                    name="name"
                                    required
                                    defaultValue={area?.name}
                                    placeholder="Contoh: Area Talasalak"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-region`}>Wilayah</Label>

                                <Input
                                    id={`${id}-region`}
                                    name="region"
                                    defaultValue={area?.region ?? ''}
                                    placeholder="Contoh: Banjarbaru Selatan"
                                />

                                <InputError message={errors.region} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-desc`}>Keterangan</Label>

                                <Textarea
                                    id={`${id}-desc`}
                                    name="description"
                                    rows={3}
                                    defaultValue={area?.description ?? ''}
                                    placeholder="Keterangan tambahan..."
                                />

                                <InputError message={errors.description} />
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

FtthAreaCodes.layout = {
    breadcrumbs: [
        {
            title: 'FTTH',
            href: '/ftth/area-codes',
        },
        {
            title: 'Kode Area',
            href: '/ftth/area-codes',
        },
    ],
};
