import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { RoleOption } from '@/types';

type EditableUser = {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;
    whatsapp: string | null;
    roles: string[];
};

export type UserFieldErrors = Partial<
    Record<
        | 'name'
        | 'email'
        | 'whatsapp'
        | 'role'
        | 'password'
        | 'password_confirmation'
        | 'avatar',
        string
    >
>;

export function UserFields({
    roles,
    errors,
    targetUser,
}: {
    roles: RoleOption[];
    errors: UserFieldErrors;
    targetUser?: EditableUser;
}) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [removeAvatar, setRemoveAvatar] = useState(false);
    const currentAvatar = preview ?? targetUser?.avatar_url ?? null;

    const currentRole =
        targetUser?.roles.find((role) =>
            roles.some((option) => option.value === role),
        ) ?? roles[0]?.value;

    useEffect(() => {
        return () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;

        setRemoveAvatar(false);

        if (preview) {
            URL.revokeObjectURL(preview);
            setPreview(null);
        }

        if (file) {
            setPreview(URL.createObjectURL(file));
        }
    };

    const handleRemoveAvatar = () => {
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }

        if (preview) {
            URL.revokeObjectURL(preview);
            setPreview(null);
        }

        setRemoveAvatar(true);
    };

    return (
        <>
            {removeAvatar && (
                <input type="hidden" name="remove_avatar" value="1" />
            )}

            <div className="flex items-center gap-4">
                {currentAvatar ? (
                    <img
                        src={currentAvatar}
                        alt={targetUser?.name ?? ''}
                        className="size-16 rounded-full object-cover"
                    />
                ) : (
                    <div className="flex size-16 items-center justify-center rounded-full bg-muted text-xl font-semibold text-muted-foreground">
                        {(targetUser?.name ?? '?').charAt(0).toUpperCase()}
                    </div>
                )}

                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => fileInputRef.current?.click()}
                        >
                            Pilih Foto
                        </Button>

                        {(targetUser?.avatar_url || preview) && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={handleRemoveAvatar}
                            >
                                Hapus Foto
                            </Button>
                        )}
                    </div>

                    <p className="text-xs text-muted-foreground">
                        JPG, PNG atau WEBP, maksimal 2MB.
                    </p>

                    <InputError message={errors.avatar} />
                </div>

                <Input
                    ref={fileInputRef}
                    type="file"
                    name="avatar"
                    accept="image/jpg,image/jpeg,image/png,image/webp"
                    className="sr-only"
                    onChange={handleFileChange}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Nama</Label>

                <Input
                    id="name"
                    name="name"
                    defaultValue={targetUser?.name}
                    required
                    autoComplete="name"
                    placeholder="Nama lengkap"
                />

                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">Email</Label>

                <Input
                    id="email"
                    type="email"
                    name="email"
                    defaultValue={targetUser?.email}
                    required
                    autoComplete="username"
                    placeholder="Alamat email"
                />

                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="whatsapp">Nomor WhatsApp</Label>

                <Input
                    id="whatsapp"
                    type="tel"
                    name="whatsapp"
                    defaultValue={targetUser?.whatsapp ?? ''}
                    autoComplete="tel"
                    placeholder="+62 812 3456 7890"
                />

                <p className="text-xs text-muted-foreground">
                    Sertakan kode negara, contoh: +62.
                </p>

                <InputError message={errors.whatsapp} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="role">Peran</Label>

                <Select name="role" defaultValue={currentRole}>
                    <SelectTrigger id="role" className="w-full">
                        <SelectValue placeholder="Pilih peran" />
                    </SelectTrigger>
                    <SelectContent
                        position="popper"
                        avoidCollisions
                        align="start"
                        className="max-h-none"
                    >
                        {roles.map((role) => (
                            <SelectItem key={role.value} value={role.value}>
                                {role.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <InputError message={errors.role} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password">
                    Password{' '}
                    {targetUser && (
                        <span className="text-muted-foreground">
                            (kosongkan jika tidak diubah)
                        </span>
                    )}
                </Label>

                <Input
                    id="password"
                    type="password"
                    name="password"
                    required={!targetUser}
                    autoComplete="new-password"
                />

                <InputError message={errors.password} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password_confirmation">
                    Konfirmasi Password
                </Label>

                <Input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required={!targetUser}
                    autoComplete="new-password"
                />

                <InputError message={errors.password_confirmation} />
            </div>
        </>
    );
}

export type { EditableUser };
