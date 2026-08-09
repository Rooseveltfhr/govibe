<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booth;
use App\Models\Exhibitor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExhibitorAdminController extends Controller
{
    public function index()
    {
        return view('admin.exhibitors.index', [
            'exhibitors' => Exhibitor::with('booth')
                ->orderByRaw("case status when 'pending' then 0 else 1 end")->orderBy('sort')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.exhibitors.form', [
            'exhibitor' => new Exhibitor(['status' => 'approved']),
            'booths'    => Booth::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $exhibitor = Exhibitor::create($this->validated($request));
        $this->syncBooth($exhibitor);

        return redirect()->route('admin.exhibitors.index')->with('ok', 'Exposant ajouté.');
    }

    public function edit(Exhibitor $exhibitor)
    {
        return view('admin.exhibitors.form', [
            'exhibitor' => $exhibitor,
            'booths'    => Booth::orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Exhibitor $exhibitor)
    {
        $previousBooth = $exhibitor->booth_id;
        $exhibitor->update($this->validated($request, $exhibitor));

        if ($previousBooth && $previousBooth !== $exhibitor->booth_id) {
            Booth::where('id', $previousBooth)->update(['status' => 'available']);
        }
        $this->syncBooth($exhibitor);

        return redirect()->route('admin.exhibitors.index')->with('ok', 'Exposant mis à jour.');
    }

    public function destroy(Exhibitor $exhibitor)
    {
        if ($exhibitor->booth_id) {
            Booth::where('id', $exhibitor->booth_id)->update(['status' => 'available']);
        }
        $exhibitor->delete();

        return back()->with('ok', 'Exposant supprimé.');
    }

    private function syncBooth(Exhibitor $exhibitor): void
    {
        if ($exhibitor->booth_id) {
            Booth::where('id', $exhibitor->booth_id)
                ->update(['status' => $exhibitor->status === 'approved' ? 'sold' : 'reserved']);
        }
    }

    private function validated(Request $request, ?Exhibitor $current = null): array
    {
        $data = $request->validate([
            'company'       => 'required|string|max:190',
            'sector'        => 'nullable|string|max:190',
            'logo_url'      => 'nullable|url|max:500',
            'banner_url'    => 'nullable|url|max:500',
            'description'   => 'nullable|string|max:5000',
            'products'      => 'nullable|string|max:3000',
            'services'      => 'nullable|string|max:3000',
            'website'       => 'nullable|url|max:190',
            'video_url'     => 'nullable|url|max:500',
            'brochure_url'  => 'nullable|url|max:500',
            'facebook'      => 'nullable|url|max:190',
            'instagram'     => 'nullable|url|max:190',
            'linkedin'      => 'nullable|url|max:190',
            'booth_id'      => 'nullable|integer|exists:booths,id',
            'contact_name'  => 'nullable|string|max:190',
            'contact_email' => 'nullable|email|max:190',
            'contact_phone' => 'nullable|string|max:60',
            'status'        => 'required|string|in:pending,approved,rejected',
            'featured'      => 'nullable|boolean',
            'sort'          => 'nullable|integer|min:0',
        ]);

        $data['socials'] = array_filter([
            'facebook'  => $data['facebook'] ?? null,
            'instagram' => $data['instagram'] ?? null,
            'linkedin'  => $data['linkedin'] ?? null,
        ]);
        unset($data['facebook'], $data['instagram'], $data['linkedin']);
        $data['featured'] = $request->boolean('featured');
        $data['sort'] = (int) ($data['sort'] ?? 0);

        if (! $current || $current->company !== $data['company']) {
            $slug = Str::slug($data['company']);
            $base = $slug;
            $i = 1;
            while (Exhibitor::where('slug', $slug)->when($current, fn ($q) => $q->where('id', '!=', $current->id))->exists()) {
                $slug = $base.'-'.(++$i);
            }
            $data['slug'] = $slug;
        }

        return $data;
    }
}
