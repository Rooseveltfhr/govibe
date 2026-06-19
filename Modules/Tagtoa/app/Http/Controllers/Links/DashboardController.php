<?php

namespace Modules\Tagtoa\App\Http\Controllers\Links;

use App\Http\Controllers\Controller;
use App\Models\Vcard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Links\Link;
use Modules\Tagtoa\App\Models\Links\LinkPage;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA Links — dashboard propriétaire.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $pages = LinkPage::where('tenant_id', Tenant::id())->withCount('links')->latest()->paginate(12);

        return view('tagtoa::links.index', compact('pages'));
    }

    public function create(): View
    {
        return view('tagtoa::links.form', ['page' => new LinkPage(), 'vcards' => $this->vcards(), 'payPages' => $this->payPages()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePage($request);
        $page = new LinkPage($data);
        $page->tenant_id = Tenant::id();
        $page->alias = $data['alias'] ?: LinkPage::generateAlias($data['title'] ?? 'links');
        if ($request->hasFile('avatar')) {
            $page->avatar_path = $request->file('avatar')->store('tagtoa/link-avatars', 'public');
        }
        $page->save();
        $this->syncLinks($page, $request);

        return redirect()->route('tagtoa.links.dashboard.edit', $page->id)->with('success', __('Page créée.'));
    }

    public function edit(int $id): View
    {
        $page = $this->own($id, ['links']);

        return view('tagtoa::links.form', ['page' => $page, 'vcards' => $this->vcards(), 'payPages' => $this->payPages()]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $page = $this->own($id);
        $data = $this->validatePage($request, $page->id);
        $data['alias'] = $data['alias'] ?: $page->alias;
        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('tagtoa/link-avatars', 'public');
        }
        $page->update($data);
        $this->syncLinks($page, $request);

        return back()->with('success', __('Page mise à jour.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->own($id)->delete();

        return redirect()->route('tagtoa.links.dashboard.index')->with('success', __('Page supprimée.'));
    }

    /* helpers */
    protected function own(int $id, array $with = []): LinkPage
    {
        return LinkPage::with($with)->where('tenant_id', Tenant::id())->findOrFail($id);
    }

    protected function validatePage(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'vcard_id'       => ['nullable', 'integer'],
            'title'          => ['nullable', 'string', 'max:160'],
            'alias'          => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:tagtoa_link_pages,alias'.($ignoreId ? ','.$ignoreId : '')],
            'bio'            => ['nullable', 'string', 'max:500'],
            'theme'          => ['nullable', 'in:dark,light,blue'],
            'donation_label' => ['nullable', 'string', 'max:80'],
            'pay_page_id'    => ['nullable', 'integer'],
            'is_active'      => ['nullable', 'boolean'],
            'avatar'         => ['nullable', 'image', 'max:2048'],
        ]);
    }

    protected function syncLinks(LinkPage $page, Request $request): void
    {
        $rows = $request->input('links', []);
        $keep = [];
        DB::transaction(function () use ($page, $rows, &$keep) {
            foreach ($rows as $i => $row) {
                if (empty($row['url']) || empty($row['label'])) {
                    continue;
                }
                $attrs = [
                    'label'       => $row['label'],
                    'url'         => $row['url'],
                    'platform'    => Link::detectPlatform($row['url']),
                    'is_featured' => ! empty($row['is_featured']),
                    'is_active'   => true,
                    'sort'        => (int) ($row['sort'] ?? $i),
                ];
                $l = ! empty($row['id']) ? $page->links()->whereKey($row['id'])->first() : null;
                $l ? $l->update($attrs) : $l = $page->links()->create($attrs);
                $keep[] = $l->id;
            }
            $page->links()->whereNotIn('id', $keep ?: [0])->delete();
        });
    }

    protected function vcards()
    {
        try {
            return Vcard::query()->orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    protected function payPages()
    {
        return PaymentPage::where('tenant_id', Tenant::id())->get(['id', 'title', 'alias']);
    }
}
