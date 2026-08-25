import { Form, Head } from '@inertiajs/react';
import { Save } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update as billingSettingUpdate } from '@/routes/billing/setting';
import type { BillingSettings } from '@/types';

type PageProps = {
    settings: BillingSettings;
};

export default function BillingSettings({ settings }: PageProps) {
    return (
        <>
            <Head title="Pengaturan Tagihan" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <Heading
                    variant="small"
                    title="Pengaturan Tagihan"
                    description="Konfigurasi siklus prorata, PPN, isolir, penagihan dan diskon promo."
                />

                <Form
                    {...billingSettingUpdate.form()}
                    options={{ preserveScroll: true }}
                    className="max-w-2xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-5 rounded-xl border p-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="prorate_cut_day">
                                        Tanggal Prorate
                                    </Label>

                                    <Input
                                        id="prorate_cut_day"
                                        name="prorate_cut_day"
                                        type="number"
                                        min={1}
                                        max={28}
                                        required
                                        defaultValue={settings.prorate_cut_day}
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        Siklus tagihan berjalan dari tanggal
                                        setelahnya sampai tanggal ini.
                                    </p>

                                    <InputError
                                        message={errors.prorate_cut_day}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="ppn_percent">PPN (%)</Label>

                                    <Input
                                        id="ppn_percent"
                                        name="ppn_percent"
                                        type="number"
                                        min={0}
                                        max={50}
                                        step="0.01"
                                        required
                                        defaultValue={settings.ppn_percent}
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        Dipisahkan dari total tagihan (DPP +
                                        PPN).
                                    </p>

                                    <InputError message={errors.ppn_percent} />
                                </div>
                            </div>

                            <div className="grid gap-5 rounded-xl border p-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="isolir_day">
                                        Tanggal Auto Isolir
                                    </Label>

                                    <Input
                                        id="isolir_day"
                                        name="isolir_day"
                                        type="number"
                                        min={1}
                                        max={28}
                                        defaultValue={
                                            settings.isolir_day ?? undefined
                                        }
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        Kosongkan untuk mematikan.
                                    </p>

                                    <InputError message={errors.isolir_day} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="isolir_hour">
                                        Jam Isolir
                                    </Label>

                                    <Input
                                        id="isolir_hour"
                                        name="isolir_hour"
                                        defaultValue={
                                            settings.isolir_hour ?? '23:59'
                                        }
                                        placeholder="23:59"
                                        inputMode="numeric"
                                        pattern="([01]\d|2[0-3]):[0-5]\d"
                                        title="Format 24 jam: HH:MM (contoh 00:01)"
                                        maxLength={5}
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        Format 24 jam (HH:MM), contoh 00:01.
                                    </p>

                                    <InputError message={errors.isolir_hour} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="isolir_profile">
                                        Profil Isolir
                                    </Label>

                                    <Input
                                        id="isolir_profile"
                                        name="isolir_profile"
                                        defaultValue={
                                            settings.isolir_profile ?? 'Isolir'
                                        }
                                        placeholder="Isolir"
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        PPP Profile khusus pelanggan
                                        tertunggak.
                                    </p>

                                    <InputError message={errors.isolir_profile} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="invoice_day">
                                        Awal Penagihan
                                    </Label>

                                    <Input
                                        id="invoice_day"
                                        name="invoice_day"
                                        type="number"
                                        min={1}
                                        max={28}
                                        required
                                        defaultValue={settings.invoice_day}
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        Tagihan bulanan diterbitkan pada tanggal
                                        ini.
                                    </p>

                                    <InputError message={errors.invoice_day} />
                                </div>
                            </div>

                            <div className="space-y-3 rounded-xl border p-4">
                                <h3 className="text-sm font-semibold">
                                    Pengingat Sebelum Auto Isolir
                                </h3>

                                <div className="flex flex-wrap items-center gap-x-8 gap-y-3">
                                    {(
                                        [
                                            {
                                                name: 'remind_h3',
                                                label: 'H-3',
                                                checked: settings.remind_h3,
                                                error: errors.remind_h3,
                                            },
                                            {
                                                name: 'remind_h2',
                                                label: 'H-2',
                                                checked: settings.remind_h2,
                                                error: errors.remind_h2,
                                            },
                                            {
                                                name: 'remind_h1',
                                                label: 'H-1',
                                                checked: settings.remind_h1,
                                                error: errors.remind_h1,
                                            },
                                        ] as const
                                    ).map((item) => (
                                        <label
                                            key={item.name}
                                            className="flex cursor-pointer items-center gap-2 text-sm font-medium"
                                        >
                                            <Checkbox
                                                name={item.name}
                                                value="1"
                                                defaultChecked={item.checked}
                                            />
                                            {item.label}
                                        </label>
                                    ))}
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Notifikasi pengingat dikirim ke pelanggan
                                    H-3, H-2 dan H-1 sebelum tanggal auto
                                    isolir.
                                </p>

                                <InputError
                                    message={
                                        errors.remind_h3 ??
                                        errors.remind_h2 ??
                                        errors.remind_h1
                                    }
                                />
                            </div>

                            <div className="grid gap-2 rounded-xl border p-4">
                                <Label htmlFor="promo_percent">
                                    Diskon Promo (%)
                                </Label>

                                <Input
                                    id="promo_percent"
                                    name="promo_percent"
                                    type="number"
                                    min={0}
                                    max={100}
                                    required
                                    defaultValue={settings.promo_percent}
                                />

                                <p className="text-xs text-muted-foreground">
                                    Hanya berlaku di awal pemasangan (invoice
                                    pertama). Bulan selanjutnya tagihan normal
                                    tanpa diskon.
                                </p>

                                <InputError message={errors.promo_percent} />
                            </div>

                            <div className="flex justify-end">
                                <Button disabled={processing}>
                                    <Save />
                                    Simpan Pengaturan
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

BillingSettings.layout = {
    breadcrumbs: [
        {
            title: 'Billing',
            href: '/billing/settings',
        },
        {
            title: 'Setting',
            href: '/billing/settings',
        },
    ],
};
