import { Form, Head, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [removeAvatar, setRemoveAvatar] = useState(false);

    const currentAvatar = preview ?? auth.user.avatar_url ?? null;

    useEffect(() => {
        return () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const handleFileChange = (
        event: React.ChangeEvent<HTMLInputElement>,
    ) => {
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
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name, email address, profile photo and WhatsApp number"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    onSuccess={() => {
                        setRemoveAvatar(false);
                        setPreview(null);
                    }}
                    className="space-y-6"
                >
                    {({ processing, progress, errors }) => (
                        <>
                            {removeAvatar && (
                                <input
                                    type="hidden"
                                    name="remove_avatar"
                                    value="1"
                                />
                            )}

                            <div className="flex items-center gap-4">
                                {currentAvatar ? (
                                    <img
                                        src={currentAvatar}
                                        alt={auth.user.name}
                                        className="size-16 rounded-full object-cover"
                                    />
                                ) : (
                                    <div className="flex size-16 items-center justify-center rounded-full bg-muted text-xl font-semibold text-muted-foreground">
                                        {auth.user.name
                                            .charAt(0)
                                            .toUpperCase()}
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled={processing}
                                            onClick={() =>
                                                fileInputRef.current?.click()
                                            }
                                        >
                                            Change photo
                                        </Button>

                                        {(auth.user.avatar_url || preview) && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={processing}
                                                onClick={handleRemoveAvatar}
                                            >
                                                Remove photo
                                            </Button>
                                        )}
                                    </div>

                                    <p className="text-xs text-muted-foreground">
                                        JPG, PNG or WEBP, max 2MB
                                    </p>

                                    <InputError
                                        message={errors.avatar}
                                    />
                                </div>

                                <Input
                                    id="avatar"
                                    ref={fileInputRef}
                                    type="file"
                                    name="avatar"
                                    accept="image/jpg,image/jpeg,image/png,image/webp"
                                    className="sr-only"
                                    onChange={handleFileChange}
                                />
                            </div>

                            {progress && (
                                <progress
                                    value={progress.percentage}
                                    max="100"
                                    className="h-1 w-full"
                                >
                                    {progress.percentage}%
                                </progress>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="whatsapp">
                                    WhatsApp number
                                </Label>

                                <Input
                                    id="whatsapp"
                                    type="tel"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.whatsapp ?? ''}
                                    name="whatsapp"
                                    autoComplete="tel"
                                    placeholder="+62 812 3456 7890"
                                />

                                <p className="text-xs text-muted-foreground">
                                    Include the country code, e.g.
                                    +62 or 08.
                                </p>

                                <InputError
                                    className="mt-2"
                                    message={errors.whatsapp}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            Your email address is unverified.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Click here to re-send the
                                                verification email.
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                A new verification link has been
                                                sent to your email address.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
