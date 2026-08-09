<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TicketCategoryController extends Controller
{
    public function index()
    {
        return view('admin.tickets.index', [
            'categories' => TicketCategory::withCount('registrations')->orderBy('sort')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.tickets.form', ['category' => new TicketCategory(['currency' => 'HTG', 'active' => true])]);
    }

    public function store(Request $request)
    {
        TicketCategory::create($this->validated($request));

        return redirect()->route('admin.tickets.index')->with('ok', 'Catégorie de billet créée.');
    }

    public function edit(TicketCategory $ticket)
    {
        return view('admin.tickets.form', ['category' => $ticket]);
    }

    public function update(Request $request, TicketCategory $ticket)
    {
        $ticket->update($this->validated($request, $ticket));

        return redirect()->route('admin.tickets.index')->with('ok', 'Catégorie mise à jour.');
    }

    public function destroy(TicketCategory $ticket)
    {
        if ($ticket->registrations()->exists()) {
            return back()->withErrors(['delete' => 'Impossible : des inscriptions utilisent cette catégorie. Désactivez-la plutôt.']);
        }
        $ticket->delete();

        return back()->with('ok', 'Catégorie supprimée.');
    }

    private function validated(Request $request, ?TicketCategory $current = null): array
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'audience'    => 'required|string|in:'.implode(',', array_keys(config('finpo.attendee_categories'))),
            'description' => 'nullable|string|max:2000',
            'price'       => 'required|integer|min:0',
            'currency'    => 'required|string|size:3',
            'quota'       => 'nullable|integer|min:1',
            'sales_start' => 'nullable|date',
            'sales_end'   => 'nullable|date|after_or_equal:sales_start',
            'color'       => 'required|string|max:9',
            'benefits'    => 'nullable|string|max:2000',
            'active'      => 'nullable|boolean',
            'sort'        => 'nullable|integer|min:0',
        ]);

        $data['benefits'] = collect(preg_split('/\r?\n/', (string) ($data['benefits'] ?? '')))
            ->map(fn ($line) => trim($line))->filter()->values()->all();
        $data['active'] = $request->boolean('active');
        $data['sort'] = $data['sort'] ?? 0;

        if (! $current || $current->name !== $data['name']) {
            $slug = Str::slug($data['name']);
            $base = $slug;
            $i = 1;
            while (TicketCategory::where('slug', $slug)->when($current, fn ($q) => $q->where('id', '!=', $current->id))->exists()) {
                $slug = $base.'-'.(++$i);
            }
            $data['slug'] = $slug;
        }

        return $data;
    }
}
