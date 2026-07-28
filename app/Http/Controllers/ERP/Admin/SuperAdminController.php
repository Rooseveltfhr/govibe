<?php

namespace App\Http\Controllers\ERP\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    // ================== USERS ==================

    public function users()
    {
        $users = User::latest()->paginate(20);
        return view('erp.admin.users', compact('users'));
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'is_admin' => 'boolean',
        ]);
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_admin'] = $request->boolean('is_admin');
        User::create($validated);
        return back()->with('success', 'Utilisateur créé avec succès.');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        $user->delete();
        return back()->with('success', 'Utilisateur supprimé.');
    }

    public function toggleAdmin(User $user)
    {
        $user->update(['is_admin' => !$user->is_admin]);
        return back()->with('success', 'Rôle admin mis à jour.');
    }

    // ================== BUSINESS UNITS ==================

    public function businessUnits()
    {
        $units = BusinessUnit::withCount('projects', 'clients')->get();
        return view('erp.admin.business-units', compact('units'));
    }

    public function storeBusinessUnit(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'icon'        => 'nullable|string|max:50',
            'color'       => 'nullable|string|max:20',
        ]);
        $validated['slug'] = \Str::slug($validated['name']);
        $validated['active'] = true;
        BusinessUnit::create($validated);
        return back()->with('success', 'Unité business créée.');
    }

    public function destroyBusinessUnit(BusinessUnit $businessUnit)
    {
        $businessUnit->delete();
        return back()->with('success', 'Unité supprimée.');
    }

    // ================== SERVICES ==================

    public function services(Request $request)
    {
        $query = Service::with('category','businessUnit');
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name','like',"%$s%")->orWhere('description','like',"%$s%"));
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        $services   = $query->latest()->paginate(20)->withQueryString();
        $categories = ServiceCategory::all();
        $businessUnits = BusinessUnit::where('active',true)->get();
        return view('erp.admin.services', compact('services','categories','businessUnits'));
    }

    public function storeService(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:1000',
            'category_id'      => 'nullable|exists:service_categories,id',
            'price'            => 'required|numeric|min:0',
            'unit'             => 'required|in:hour,day,month,project,session',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'is_active'        => 'boolean',
        ]);
        $validated['slug']      = \Str::slug($validated['name']) . '-' . time();
        $validated['is_active'] = $request->boolean('is_active', true);
        Service::create($validated);
        return back()->with('success', 'Service créé avec succès.');
    }

    public function updateService(Request $request, Service $service)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:1000',
            'category_id'      => 'nullable|exists:service_categories,id',
            'price'            => 'required|numeric|min:0',
            'unit'             => 'required|in:hour,day,month,project,session',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'is_active'        => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        $service->update($validated);
        return back()->with('success', 'Service mis à jour.');
    }

    public function destroyService(Service $service)
    {
        $service->delete();
        return back()->with('success', 'Service supprimé.');
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'icon'  => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);
        $validated['slug'] = \Str::slug($validated['name']);
        ServiceCategory::create($validated);
        return back()->with('success', 'Catégorie créée.');
    }
}
