import { Form, Head, usePage } from '@inertiajs/react';
import { MessageSquareText, Pencil, Save } from 'lucide-react';
import { useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store as waTemplateStore } from '@/routes/wa/template';

type TemplateRow = {
    id: number;
    slug: string;
    name: string;
    body: string;
    variables_hint: string | null;
};

export default function WaTemplates() {
    const [editing, setEditing] = useState<TemplateRow | null>(null);

    const templates = usePage<{
        templates: TemplateRow[];
    }>().props.templates;

    return (
        <>
            <Head title="Template WhatsApp" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <Heading
                    variant="small"
                    title="Template WhatsApp"
                    description="Pesan otomatis platform: PSB, pembayaran, gangguan dan pengingat isolir."
                />

                <p className="max-w-2xl rounded-xl border border-dashed p-3 text-xs leading-5 text-muted-foreground">
                    Variabel tersedia:{' '}
                    {[
                        '{{nama}}',
                        '{{alamat}}',
                        '{{area}}',
                        '{{paket}}',
                        '{{harga}}',
                        '{{username}}',
                        '{{jadwal}}',
                        '{{teknisi}}',
                        '{{tagihan}}',
                        '{{periode}}',
                        '{{total}}',
                        '{{metode}}',
                        '{{tanggal_isolir}}',
                        '{{jam_isolir}}',
                    ].map((variable) => (
                        <code
                            key={variable}
                            className="mx-0.5 rounded bg-muted px-1"
                        >
                            {variable}
                        </code>
                    ))}
                </p>

                <div className="grid gap-4 lg:grid-cols-2">
                    {templates.map((template) => (
                        <TemplateCard
                            key={template.id}
                            template={template}
                            onEdit={() => setEditing(template)}
                        />
                    ))}
                </div>

                {editing && (
                    <EditDialog
                        template={editing}
                        onClose={() => setEditing(null)}
                    />
                )}
            </div>
        </>
    );
}

function TemplateCard({
    template,
    onEdit,
}: {
    template: TemplateRow;
    onEdit: () => void;
}) {
    return (
        <div className="space-y-3 rounded-xl border p-4">
            <div className="flex items-center justify-between gap-2">
                <h3 className="flex items-center gap-2 text-sm font-semibold">
                    <MessageSquareText className="size-4" />
                    {template.name}
                </h3>
                <Badge variant="outline">{template.slug}</Badge>
            </div>

            {template.variables_hint && (
                <p className="text-xs text-muted-foreground">
                    {template.variables_hint}
                </p>
            )}

            <pre className="scrollbar-slim max-h-40 overflow-auto rounded-lg bg-muted/40 p-3 text-xs whitespace-pre-wrap">
                {template.body}
            </pre>

            <Button variant="outline" size="sm" onClick={onEdit}>
                <Pencil />
                Edit Template
            </Button>
        </div>
    );
}

function EditDialog({
    template,
    onClose,
}: {
    template: TemplateRow;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit {template.name}</DialogTitle>
                    <DialogDescription>
                        Slug: <code>{template.slug}</code>. Gunakan variabel{' '}
                        {'{{...}}'} yang tersedia.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...waTemplateStore.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="slug"
                                value={template.slug}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor={`name-${template.id}`}>
                                    Nama
                                </Label>

                                <Input
                                    id={`name-${template.id}`}
                                    name="name"
                                    required
                                    defaultValue={template.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`body-${template.id}`}>
                                    Body Pesan
                                </Label>

                                <Textarea
                                    id={`body-${template.id}`}
                                    name="body"
                                    rows={8}
                                    required
                                    defaultValue={template.body}
                                />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="outline" type="button">
                                        Batal
                                    </Button>
                                </DialogClose>

                                <Button disabled={processing}>
                                    <Save />
                                    Simpan Template
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
