<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        return view('admin.coupons.index', ['coupons' => Coupon::latest()->get()]);
    }

    public function create()
    {
        return view('admin.coupons.form', ['coupon' => new Coupon(['type' => 'percent', 'active' => true])]);
    }

    public function store(Request $request)
    {
        Coupon::create($this->validated($request));

        return redirect()->route('admin.coupons.index')->with('ok', 'Code promo créé.');
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.form', ['coupon' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($this->validated($request, $coupon));

        return redirect()->route('admin.coupons.index')->with('ok', 'Code promo mis à jour.');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return back()->with('ok', 'Code promo supprimé.');
    }

    private function validated(Request $request, ?Coupon $current = null): array
    {
        $data = $request->validate([
            'code'       => 'required|string|max:60|unique:coupons,code'.($current ? ','.$current->id : ''),
            'type'       => 'required|string|in:percent,fixed',
            'value'      => 'required|integer|min:1',
            'max_uses'   => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'active'     => 'nullable|boolean',
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
