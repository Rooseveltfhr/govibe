<?php

namespace App\Http\Controllers;

use App\Models\TaGtoaLink;
use App\Models\TaGtoaLinkPage;
use App\Models\TaGtoaPaymentPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * TAGTOA LINKS — dashboard propriétaire.
 *
 * Pages scopées au tenant via BelongsToTenant sur TaGtoaLinkPage.
 */
class TaGtoaLinkDashboardController extends Controller
{
    public function index(): View
    {
        $pages = TaGtoaLinkPage::withCount('links')->latest()->paginate(15);

        return view('tagtoa.links.dashboard.index', compact('pages'));
    }

    public function create(): View
    {
        return view('tagtoa.links.dashboard.form', [
            'page'     => new TaGtoaLinkPage(),
            'vcards'   => $this->ownerVcards(),
            'payPages' => $this->ownerPayPages(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePage($request);

        $page = new TaGtoaLinkPage($data);
        $page->tenant_id = getLogInTenantId();
        $page->alias     = $data['alias'] ?: TaGtoaLinkPage::generateAlias($data['title'] ?? 'links');
        $page->save();

        if ($request->hasFile('avatar')) {
            $page->addMediaFromRequest('avatar')->toMediaCollection('avatar');
        }

        $this->syncLinks($page, $request);

        return redirect()
            ->route('tagtoa.links.dashboard.edit', $page->id)
            ->with('success', __('Page LINKS créée.'));
    }

    public function edit(int $id): View
    {
        $page = TaGtoaLinkPage::with(['links', 'media'])->findOrFail($id);

        return view('tagtoa.links.dashboard.form', [
            'page'     => $page,
            'vcards'   => $this->ownerVcards(),
            'payPages' => $this->ownerPayPages(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $page = TaGtoaLinkPage::findOrFail($id);
        $data = $this->validatePage($request, $page->id);

        $data['alias'] = $data['alias'] ?: $page->alias;
        $page->update($data);

        if ($request->hasFile('avatar')) {
            $page->clearMediaCollection('avatar');
            $page->addMediaFromRequest('avatar')->toMediaCollection('avatar');
        }

        $this->syncLinks($page, $request);

        return back()->with('success', __('Page mise à jour.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        TaGtoaLinkPage::findOrFail($id)->delete();

        return redirect()
            ->route('tagtoa.links.dashboard.index')
            ->with('success', __('Page supprimée.'));
    }

    /* ----------------------------------------------------------------- Helpers */

    protected function validatePage(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'vcard_id'       => ['nullable', 'integer', 'exists:vcards,id'],
            'title'          => ['nullable', 'string', 'max:160'],
            'alias'          => [
                'nullable', 'string', 'max:120', 'alpha_dash',
                'unique:tagtoa_link_pages,alias' . ($ignoreId ? ',' . $ignoreId : ''),
            ],
            'bio'            => ['nullable', 'string', 'max:500'],
            'theme'          => ['nullable', 'in:dark,light,blue'],
            'donation_label' => ['nullable', 'string', 'max:80'],
            'pay_page_id'    => ['nullable', 'integer', 'exists:tagtoa_payment_pages,id'],
            'is_active'      => ['nullable', 'boolean'],
            'avatar'         => ['nullable', 'image', 'max:2048'],
        ]);
    }

    /** Synchronise les liens (tableau `links[]`) avec auto-détection de plateforme. */
    protected function syncLinks(TaGtoaLinkPage $page, Request $request): void
    {
        $rows    = $request->input('links', []);
        $keepIds = [];

        DB::transaction(function () use ($page, $rows, &$keepIds) {
            foreach ($rows as $i => $row) {
                if (empty($row['url']) || empty($row['label'])) {
                    continue;
                }

                $attributes = [
                    'label'       => $row['label'],
                    'url'         => $row['url'],
                    'platform'    => TaGtoaLink::detectPlatform($row['url']),
                    'is_featured' => ! empty($row['is_featured']),
                    'is_active'   => ! empty($row['is_active']),
                    'sort'        => (int) ($row['sort'] ?? $i),
                ];

                $link = ! empty($row['id'])
                    ? $page->links()->whereKey($row['id'])->first()
                    : null;

                if ($link) {
                    $link->update($attributes);
                } else {
                    $link = $page->links()->create($attributes);
                }

                $keepIds[] = $link->id;
            }

            $page->links()->whereNotIn('id', $keepIds ?: [0])->delete();
        });
    }

    protected function ownerVcards()
    {
        return \App\Models\Vcard::query()->orderBy('name')->get(['id', 'name']);
    }

    protected function ownerPayPages()
    {
        return TaGtoaPaymentPage::query()->orderBy('title')->get(['id', 'title', 'alias']);
    }
}
