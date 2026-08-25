import { Form, Head, usePage } from '@inertiajs/react';
import { Megaphone, Radio } from 'lucide-react';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { store as broadcastStore } from '@/routes/wa/broadcast';

type BroadcastRow = {
    id: number;
    message: string;
    area_code: string | null;
    area_name: string | null;
    target_all: boolean;
    recipients: number;
    sent_count: number;
    failed_count: number;
    sent_at: string | null;
    operator: string | null;
};

type AreaOption = { id: number; code: string; name: string };
type TemplateOption = { id: number; slug: string; name: string; body: string };

export default function WaBroadcasts() {
    const [scopeArea, setScopeArea] = useState('all');
    const [templateId, setTemplateId] = useState('');

    const page = usePage<{
        broadcasts: {
            data: BroadcastRow[];
            links: { url: string | null; label: string; active: boolean }[];
            current_page: number;
            last_page: number;
        };
        areas: AreaOption[];
        templates: TemplateOption[];
    }>().props;

    const [createOpen, setCreateOpen] = useState(false);

    return (
        <>
            <Head title="Broadcast WhatsApp" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Broadcast WhatsApp"
                        description="Kirim pesan massal ke pelanggan aktif berdasarkan kode area atau seluruh area."
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        <Megaphone />
                        Buat Broadcast
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[860px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Pesan</th>
                                <th className="px-4 py-3 font-medium">
                                    Cakupan
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Penerima
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Terkirim / Gagal
                                </th>
                                <th className="px-4 py-3 font-medium">Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            {page.broadcasts.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada broadcast.
                                    </td>
                                </tr>
                            )}

                            {page.broadcasts.data.map((broadcast) => (
                                <tr
                                    key={broadcast.id}
                                    className="border-t align-top"
                                >
                                    <td className="max-w-[320px] px-4 py-3">
                                        <p className="whitespace-pre-line">
                                            {broadcast.message}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {broadcast.target_all ? (
                                            <Badge variant="outline">
                                                Semua Area
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                {broadcast.area_code}
                                            </Badge>
                                        )}
                                        {!broadcast.target_all && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                {broadcast.area_name}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {broadcast.recipients}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                        <span className="text-emerald-600 dark:text-emerald-400">
                                            ✓ {broadcast.sent_count}
                                        </span>{' '}
                                        <span className="text-destructive">
                                            ✗ {broadcast.failed_count}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs whitespace-nowrap text-muted-foreground">
                                        {broadcast.operator}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Buat Broadcast</DialogTitle>
                        <DialogDescription>
                            Pesan dikirim via WAHA ke seluruh pelanggan aktif
                            pada cakupan yang dipilih. Variabel {'{{nama}}'},{' '}
                            {'{{paket}}'}, {'{{area}}'} otomatis diisi per
                            pelanggan.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        {...broadcastStore.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setCreateOpen(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                {scopeArea !== 'all' && (
                                    <input
                                        type="hidden"
                                        name="area_code_id"
                                        value={scopeArea}
                                    />
                                )}
                                {templateId && (
                                    <input
                                        type="hidden"
                                        name="message_template_id"
                                        value={templateId}
                                    />
                                )}
                                <div className="grid gap-2">
                                    <Label>Cakupan</Label>

                                    <Select
                                        value={scopeArea}
                                        onValueChange={setScopeArea}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Semua area" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                Semua Area
                                            </SelectItem>
                                            {page.areas.map((area) => (
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
                                    <Label>Template (opsional)</Label>

                                    <Select
                                        value={templateId}
                                        onValueChange={setTemplateId}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Tulis pesan manual" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {page.templates.map((template) => (
                                                <SelectItem
                                                    key={template.id}
                                                    value={String(template.id)}
                                                >
                                                    {template.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <p className="text-xs text-muted-foreground">
                                        Jika template dipilih, isi pesan
                                        dikosongkan agar body template
                                        digunakan.
                                    </p>

                                    <InputError
                                        message={errors.message_template_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="message">
                                        Pesan Manual{' '}
                                        <span className="text-muted-foreground">
                                            (opsional jika memilih template)
                                        </span>
                                    </Label>

                                    <Textarea
                                        id="message"
                                        name="message"
                                        rows={5}
                                        placeholder="Contoh: Pelanggan Yth, akan ada maintenance jaringan malam ini..."
                                    />

                                    <InputError message={errors.message} />
                                </div>

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button variant="outline" type="button">
                                            Batal
                                        </Button>
                                    </DialogClose>

                                    <Button disabled={processing}>
                                        <Radio />
                                        Kirim Broadcast
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
