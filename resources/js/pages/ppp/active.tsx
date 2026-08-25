import { Head, router } from '@inertiajs/react';
import { Activity, PowerOff, Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    destroy as pppActiveDestroy,
    index as pppActivesIndex,
} from '@/routes/ppp/active';
import type {
    PppActiveSession,
    PppFilters,
    PppPaginated,
    PppServerBadge,
} from '@/types';

type PageProps = {
    sessions: PppPaginated<PppActiveSession>;
    filters: PppFilters;
    mikrotik_server: PppServerBadge;
    error: string | null;
};

export default function PppActive({
    sessions,
    filters,
    mikrotik_server: mikrotikServer,
    error,
}: PageProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            pppActivesIndex(),
            { search },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="PPP Active" />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="PPP Active"
                        description="Sesi PPPoE yang sedang aktif pada router utama."
                    />

                    <Button
                        variant="outline"
                        onClick={() =>
                            router.get(
                                pppActivesIndex(),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Activity />
                        Muat Ulang
                    </Button>
                </div>

                {mikrotikServer && (
                    <div className="flex items-center gap-2 rounded-xl border p-4 text-sm">
                        Router:
                        <Badge variant="secondary">{mikrotikServer.name}</Badge>
                    </div>
                )}

                <form
                    onSubmit={submitSearch}
                    className="relative w-full sm:w-72"
                >
                    <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />

                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="pl-8"
                        placeholder="Cari user / caller-id / address..."
                    />
                </form>

                {error && (
                    <p className="rounded-xl border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        {error}
                    </p>
                )}

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">User</th>
                                <th className="px-4 py-3 font-medium">
                                    Service
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Caller ID
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Address
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Uptime
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {sessions.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Tidak ada sesi aktif.
                                    </td>
                                </tr>
                            )}

                            {sessions.data.map((session) => (
                                <tr key={session.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {session.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="secondary">
                                            {session.service}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 break-all">
                                        {session.caller_id}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {session.address}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {session.uptime}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() =>
                                                router.delete(
                                                    pppActiveDestroy(
                                                        session.id,
                                                    ),
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                )
                                            }
                                        >
                                            <PowerOff />
                                            Putuskan
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {sessions.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Halaman {sessions.current_page} dari{' '}
                            {sessions.last_page} ({sessions.total} sesi)
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!sessions.links[0]?.url}
                                onClick={() =>
                                    sessions.links[0]?.url &&
                                    router.visit(sessions.links[0].url, {
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
                                    !sessions.links[sessions.links.length - 1]
                                        ?.url
                                }
                                onClick={() =>
                                    sessions.links[sessions.links.length - 1]
                                        ?.url &&
                                    router.visit(
                                        sessions.links[
                                            sessions.links.length - 1
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
        </>
    );
}

PppActive.layout = {
    breadcrumbs: [
        {
            title: 'PPPoE',
            href: '/ppp/actives',
        },
        {
            title: 'PPP Active',
            href: '/ppp/actives',
        },
    ],
};
