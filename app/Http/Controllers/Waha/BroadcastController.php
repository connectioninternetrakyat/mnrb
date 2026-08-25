<?php

namespace App\Http\Controllers\Waha;

use App\Enums\PelangganStatus;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\AreaCode;
use App\Models\Broadcast;
use App\Models\MessageTemplate;
use App\Models\Pelanggan;
use App\Services\Waha\TemplateRenderer;
use App\Services\Waha\WahaSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    /**
     * Display the broadcast history.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::WaView->value);

        $broadcasts = Broadcast::query()
            ->with([
                'user:id,name',
                'areaCode:id,code,name',
            ])
            ->orderByDesc('created_at')
            ->paginate(10)
            ->through(fn (Broadcast $broadcast): array => [
                'id' => $broadcast->id,
                'message' => $broadcast->message,
                'area_code' => $broadcast->areaCode?->code,
                'area_name' => $broadcast->areaCode?->name,
                'target_all' => $broadcast->area_code_id === null,
                'recipients' => $broadcast->recipients,
                'sent_count' => $broadcast->sent_count,
                'failed_count' => $broadcast->failed_count,
                'sent_at' => $broadcast->created_at?->toISOString(),
                'operator' => $broadcast->user?->name,
            ]);

        return Inertia::render('wa/broadcasts', [
            'broadcasts' => $broadcasts,
            'areas' => AreaCode::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (AreaCode $area): array => [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                ])->all(),
            'templates' => MessageTemplate::query()
                ->orderBy('slug')
                ->get(['id', 'slug', 'name', 'body'])
                ->map(fn (MessageTemplate $template): array => [
                    'id' => $template->id,
                    'slug' => $template->slug,
                    'name' => $template->name,
                    'body' => $template->body,
                ])->all(),
        ]);
    }

    /**
     * Launch a broadcast to active pelanggan of the chosen scope.
     */
    public function store(WahaSender $sender): RedirectResponse
    {
        Gate::authorize(UserPermission::WaSend->value);

        $data = request()->validate([
            'area_code_id' => ['nullable', Rule::exists('area_codes', 'id')],
            'message_template_id' => ['nullable', Rule::exists('message_templates', 'id')],
            'message' => ['required_without:message_template_id', 'nullable', 'string', 'max:4000'],
        ]);

        $scopeArea = isset($data['area_code_id']) && $data['area_code_id'] !== null
            ? (int) $data['area_code_id']
            : null;

        $recipients = Pelanggan::query()
            ->where('status', PelangganStatus::Active->value)
            ->when($scopeArea !== null, fn ($query) => $query->where('area_code_id', $scopeArea))
            ->whereNotNull('whatsapp')
            ->with(['package:id,name', 'areaCode:id,code,name'])
            ->get();

        if ($recipients->isEmpty()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Tidak ada pelanggan aktif pada cakupan tersebut.')]);

            return back();
        }

        $template = isset($data['message_template_id']) && $data['message_template_id'] !== null
            ? MessageTemplate::find((int) $data['message_template_id'])
            : null;

        $customBody = filled($data['message'] ?? null) ? (string) $data['message'] : null;
        $baseBody = $customBody ?? ($template?->body ?? '');

        $broadcast = Broadcast::create([
            'user_id' => request()->user()->id,
            'area_code_id' => $scopeArea,
            'message_template_id' => $template?->id,
            'message' => $baseBody,
            'recipients' => $recipients->count(),
        ]);

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $pelanggan) {
            $text = TemplateRenderer::render(
                $baseBody,
                TemplateRenderer::varsFor($pelanggan),
            );

            if ($sender->send($pelanggan->whatsapp, $text)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $broadcast->update([
            'sent_count' => $sent,
            'failed_count' => $failed,
            'sent_at' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => $failed > 0 ? 'error' : 'success',
            'message' => __('Broadcast terkirim ke :sent pelanggan (:failed gagal).', ['sent' => $sent, 'failed' => $failed]),
        ]);

        return back();
    }
}
