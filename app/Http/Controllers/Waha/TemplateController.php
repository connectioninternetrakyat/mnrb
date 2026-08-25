<?php

namespace App\Http\Controllers\Waha;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TemplateController extends Controller
{
    /**
     * Display the WhatsApp message templates.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::WaView->value);

        $templates = MessageTemplate::query()
            ->orderBy('slug')
            ->get()
            ->map(fn (MessageTemplate $template): array => [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
                'body' => $template->body,
                'variables_hint' => $template->variables_hint,
            ]);

        return Inertia::render('wa/templates', [
            'templates' => $templates,
        ]);
    }

    /**
     * Create or update a template by slug.
     */
    public function store(): RedirectResponse
    {
        Gate::authorize(UserPermission::WaManage->value);

        $data = request()->validate([
            'slug' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        try {
            MessageTemplate::updateOrCreate(
                ['slug' => $data['slug']],
                ['name' => $data['name'], 'body' => $data['body']],
            );
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template :name tersimpan.', ['name' => $data['name']])]);

        return back();
    }

    /**
     * Delete a template.
     */
    public function destroy(MessageTemplate $template): RedirectResponse
    {
        Gate::authorize(UserPermission::WaManage->value);

        if (in_array($template->slug, MessageTemplate::SLUGS, true)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Template bawaan tidak dapat dihapus. Isi ulang body-nya saja.')]);

            return back();
        }

        $template->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template dihapus.')]);

        return back();
    }
}
