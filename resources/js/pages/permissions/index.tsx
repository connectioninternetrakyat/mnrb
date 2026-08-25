import { Head, router, useForm, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    index as permissionsIndex,
    update as permissionsUpdate,
} from '@/routes/permissions';
import type { MenuOption, RoleDetail } from '@/types';

type SelectedRole = {
    id: number;
    name: string;
    menus: string[];
};

type PageProps = {
    roles: RoleDetail[];
    menus: MenuOption[];
    filters: {
        role?: string | null;
    };
    selectedRole: SelectedRole | null;
};

export default function PermissionsIndex() {
    const { roles, menus, filters, selectedRole } = usePage<PageProps>().props;

    const handleRoleChange = (name: string) => {
        router.get(permissionsIndex({ query: { role: name } }), undefined, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Permission" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="max-w-xl space-y-6">
                    <Heading
                        variant="small"
                        title="Permission"
                        description="Tentukan menu sidebar yang dapat diakses oleh setiap role."
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="role-select">Role</Label>

                        <Select
                            value={
                                selectedRole?.name ?? filters.role ?? undefined
                            }
                            onValueChange={handleRoleChange}
                        >
                            <SelectTrigger id="role-select" className="w-full">
                                <SelectValue placeholder="Pilih role" />
                            </SelectTrigger>
                            <SelectContent
                                position="popper"
                                avoidCollisions
                                align="start"
                                className="max-h-none"
                            >
                                {roles.map((role) => (
                                    <SelectItem key={role.id} value={role.name}>
                                        {role.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {selectedRole ? (
                        <MenuPermissionPanel
                            key={selectedRole.id}
                            role={selectedRole}
                            menus={menus}
                        />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Belum ada role tersedia. Tambahkan role terlebih
                            dahulu di halaman Role.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

function MenuPermissionPanel({
    role,
    menus,
}: {
    role: SelectedRole;
    menus: MenuOption[];
}) {
    const form = useForm({
        menus: role.menus,
    });

    const toggleMenu = (value: string, checked: boolean) => {
        form.setData(
            'menus',
            checked
                ? [...form.data.menus, value]
                : form.data.menus.filter((menu) => menu !== value),
        );
    };

    return (
        <div className="space-y-4 rounded-xl border p-4">
            <div className="flex items-center gap-2">
                <KeyRound className="size-4 text-muted-foreground" />
                <p className="text-sm font-medium">
                    Menu untuk role{' '}
                    <span className="font-semibold">{role.name}</span>
                </p>
            </div>

            <div className="space-y-3">
                {menus.map((menu) => (
                    <div key={menu.value} className="flex items-center gap-2">
                        <Checkbox
                            id={`menu-${menu.value}`}
                            checked={form.data.menus.includes(menu.value)}
                            onCheckedChange={(checked) =>
                                toggleMenu(menu.value, checked === true)
                            }
                        />
                        <Label
                            htmlFor={`menu-${menu.value}`}
                            className="font-normal"
                        >
                            {menu.label}
                        </Label>
                    </div>
                ))}
            </div>

            <InputError message={form.errors.menus} />

            <Button
                disabled={form.processing}
                onClick={() =>
                    form.put(permissionsUpdate.url({ role: role.id }))
                }
            >
                Simpan
            </Button>
        </div>
    );
}

PermissionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Permission',
            href: permissionsIndex(),
        },
    ],
};
