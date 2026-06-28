<?php

namespace Modules\Tagtoa\App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Vcard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Links\Link;
use Modules\Tagtoa\App\Models\Links\LinkPage;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Site\Site;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA SITE — dashboard propriétaire (création de site web par abonnement).
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $sites = Site::where('tenant_id', Tenant::id())->latest()->paginate(12);

        return view('tagtoa::site.index', compact('sites'));
    }

    public function create(): View
    {
        return view('tagtoa::site.form', $this->formData(new Site([
            'theme' => 'light', 'accent_color' => '#16A34A',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSite($request);
        $site = new Site($this->fill($data, $request));
        $site->tenant_id = Tenant::id();
        $site->alias = $data['alias'] ?: Site::generateAlias($data['name'] ?? 'site');
        $this->uploads($site, $request);
        $site->save();

        return redirect()->route('tagtoa.site.dashboard.edit', $site->id)
            ->with('success', __('Site créé. Personnalisez-le.'));
    }

    public function edit(int $id): View
    {
        return view('tagtoa::site.form', $this->formData($this->own($id)));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $site = $this->own($id);
        $data = $this->validateSite($request, $site->id);
        $site->fill($this->fill($data, $request));
        $site->alias = $data['alias'] ?: $site->alias;
        $this->uploads($site, $request);
        $site->save();

        return back()->with('success', __('Site mis à jour.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->own($id)->delete();

        return redirect()->route('tagtoa.site.dashboard.index')->with('success', __('Site supprimé.'));
    }

    /* ---------------------------------------------------------------- helpers */

    protected function own(int $id): Site
    {
        return Site::where('tenant_id', Tenant::id())->findOrFail($id);
    }

    protected function formData(Site $site): array
    {
        $tid = Tenant::id();

        return [
            'site'     => $site,
            'vcards'   => $this->vcards(),
            'menus'    => Menu::where('tenant_id', $tid)->get(['id', 'name']),
            'payPages' => PaymentPage::where('tenant_id', $tid)->get(['id', 'title', 'alias']),
            'linkPages'=> LinkPage::where('tenant_id', $tid)->get(['id', 'title', 'alias']),
            'platforms'=> array_keys(Link::PLATFORM_ICONS),
        ];
    }

    protected function validateSite(Request $request, ?int $ignoreId = null): array
    {
        $tid = Tenant::id();

        return $request->validate([
            'vcard_id'     => ['nullable', 'integer', Rule::in($this->vcards()->pluck('id')->all())],
            'name'         => ['required', 'string', 'max:160'],
            'alias'        => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:tagtoa_sites,alias'.($ignoreId ? ','.$ignoreId : '')],
            'tagline'      => ['nullable', 'string', 'max:200'],
            'about'        => ['nullable', 'string', 'max:2000'],
            'theme'        => ['nullable', Rule::in(Site::THEMES)],
            'accent_color' => ['nullable', 'string', 'max:16'],
            'phone'        => ['nullable', 'string', 'max:40'],
            'whatsapp'     => ['nullable', 'string', 'max:40'],
            'email'        => ['nullable', 'email', 'max:160'],
            'address'      => ['nullable', 'string', 'max:200'],
            'map_url'      => ['nullable', 'string', 'max:500'],
            'menu_id'      => ['nullable', 'integer', Rule::in(Menu::where('tenant_id', $tid)->pluck('id')->all())],
            'pay_page_id'  => ['nullable', 'integer', Rule::in(PaymentPage::where('tenant_id', $tid)->pluck('id')->all())],
            'link_page_id' => ['nullable', 'integer', Rule::in(LinkPage::where('tenant_id', $tid)->pluck('id')->all())],
            'services'     => ['nullable', 'array'],
            'hours'        => ['nullable', 'array'],
            'socials'      => ['nullable', 'array'],
            'show_services'=> ['nullable', 'boolean'],
            'show_hours'   => ['nullable', 'boolean'],
            'show_gallery' => ['nullable', 'boolean'],
            'show_contact' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'logo'         => ['nullable', 'image', 'max:2048'],
            'cover'        => ['nullable', 'image', 'max:4096'],
            'gallery_files'=> ['nullable', 'array'],
            'gallery_files.*' => ['image', 'max:4096'],
        ]);
    }

    /** Nettoie les blocs répétables (supprime les lignes vides). */
    protected function fill(array $data, Request $request): array
    {
        $data['services'] = collect($request->input('services', []))
            ->filter(fn ($s) => ! empty($s['title']))
            ->map(fn ($s) => ['icon' => $s['icon'] ?? 'fa-solid fa-star', 'title' => $s['title'], 'desc' => $s['desc'] ?? ''])
            ->values()->all();

        $data['hours'] = collect($request->input('hours', []))
            ->filter(fn ($h) => ! empty($h['day']))
            ->map(fn ($h) => ['day' => $h['day'], 'value' => $h['value'] ?? ''])
            ->values()->all();

        $data['socials'] = collect($request->input('socials', []))
            ->filter(fn ($s) => ! empty($s['url']))
            ->map(fn ($s) => ['platform' => $s['platform'] ?? 'website', 'url' => $s['url']])
            ->values()->all();

        return $data;
    }

    protected function uploads(Site $site, Request $request): void
    {
        if ($request->hasFile('logo')) {
            $site->logo_path = $request->file('logo')->store('tagtoa/site-logos', 'public');
        }
        if ($request->hasFile('cover')) {
            $site->cover_path = $request->file('cover')->store('tagtoa/site-covers', 'public');
        }
        if ($request->hasFile('gallery_files')) {
            $gallery = $site->gallery ?? [];
            foreach ($request->file('gallery_files') as $img) {
                $gallery[] = $img->store('tagtoa/site-gallery', 'public');
            }
            $site->gallery = array_slice($gallery, 0, 12); // borne raisonnable
        }
    }

    protected function vcards()
    {
        try {
            return Vcard::query()->where('tenant_id', Tenant::id())->orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
