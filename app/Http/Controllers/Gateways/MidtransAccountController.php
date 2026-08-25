<?php

namespace App\Http\Controllers\Gateways;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gateways\MidtransAccountRequest;
use App\Models\MidtransAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MidtransAccountController extends Controller
{
    /**
     * Display the Midtrans accounts list.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::GatewaysView->value);

        return Inertia::render('gateways/midtrans', [
            'accounts' => $this->accounts(),
            'webhook_url' => $this->webhookUrl(),
            'detail' => null,
        ]);
    }

    /**
     * Store a newly created Midtrans account.
     */
    public function store(MidtransAccountRequest $request): RedirectResponse
    {
        MidtransAccount::create($this->credentials($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Akun Midtrans berhasil ditambahkan.')]);

        return back();
    }

    /**
     * Update the given Midtrans account.
     */
    public function update(MidtransAccountRequest $request, MidtransAccount $account): RedirectResponse
    {
        $account->update($this->credentials($request, $account->server_key));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Akun Midtrans berhasil diperbarui.')]);

        return back();
    }

    /**
     * Remove the given Midtrans account.
     */
    public function destroy(MidtransAccount $account): RedirectResponse
    {
        $account->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Akun Midtrans berhasil dihapus.')]);

        return back();
    }

    /**
     * Merge validated input with credential-keep-on-blank semantics.
     *
     * @return array<string, mixed>
     */
    private function credentials(MidtransAccountRequest $request, ?string $currentServerKey = null): array
    {
        $data = $request->safe()->except(['server_key']);

        if ($request->filled('server_key')) {
            $data['server_key'] = (string) $request->input('server_key');
        } elseif ($currentServerKey !== null) {
            $data['server_key'] = $currentServerKey;
        }

        return $data;
    }

    /**
     * Build the payment notification URL from the configured APP_URL.
     */
    private function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/webhooks/midtrans';
    }

    /**
     * Shape all accounts for the frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    private function accounts(): array
    {
        return MidtransAccount::query()
            ->orderBy('name')
            ->get()
            ->map(fn (MidtransAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'is_active' => $account->is_active,
                'is_production' => $account->is_production,
                'merchant_id' => $account->merchant_id,
                'client_key' => $account->client_key,
                'has_server_key' => $account->server_key !== null && $account->server_key !== '',
                'last_notified_at' => $account->last_notified_at?->toIso8601String(),
            ])
            ->all();
    }
}
