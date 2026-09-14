<?php

namespace Modules\Tagtoa\App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Shop\ShopItem;
use Modules\Tagtoa\App\Models\Shop\ShopOrder;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Support\Order\OrderStatus;

/**
 * BOUTIQUE TAGTOA — côté fondateur : le catalogue et les commandes reçues.
 *
 * ⚠️ SORTIE D'ISOLATION ASSUMÉE. Cet écran voit les commandes de TOUS les
 * commerces, et c'est sa raison d'être : c'est TAGTOA qui expédie. La sortie
 * est donc NOMMÉE (`allTenants()`) plutôt que subie, et elle ne vit que
 * derrière le rôle super-admin, dans ce contrôleur et nulle part ailleurs.
 *
 * Ce que le fondateur peut faire, et pourquoi c'est exactement cela :
 *   • fixer le TRANSPORT, qui n'est connu qu'une fois qu'on sait où livrer ;
 *   • faire avancer le statut, et rien d'autre.
 *
 * Il ne peut PAS retoucher les lignes d'une commande. Changer une quantité ou
 * un prix après coup, c'est réécrire ce que le marchand a accepté — et le seul
 * témoin de la transaction disparaît. Une commande fausse s'annule et se
 * repasse.
 */
class ShopAdminController extends Controller
{
    /* ---------------- Le catalogue ---------------- */

    public function index(): View
    {
        return view('tagtoa::superadmin.shop', [
            'items'  => ShopItem::orderBy('sort')->orderBy('id')->get(),
            // Les commandes de tous les commerces : la sortie d'isolation est
            // ici, en toutes lettres, et seulement ici.
            'orders' => ShopOrder::allTenants()->with('items')
                ->orderByDesc('placed_at')->orderByDesc('id')->paginate(30),
            'statuts' => OrderStatus::LABELS,
        ]);
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $data = $this->reglesArticle($request);

        ShopItem::create($data + ['sort' => (int) ShopItem::max('sort') + 1, 'is_active' => true]);

        return back()->with('success', __('Article « :nom » ajouté à la boutique.', ['nom' => $data['name']]));
    }

    public function updateItem(Request $request, int $id): RedirectResponse
    {
        $item = ShopItem::findOrFail($id);
        $data = $this->reglesArticle($request, $item->id);

        $item->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Article mis à jour.'));
    }

    /* ---------------- Les commandes ---------------- */

    /**
     * Confirme une commande : transport, réponse, et statut qui avance.
     *
     * Le TOTAL est recalculé côté serveur à partir du sous-total figé plus le
     * transport saisi. Il n'est jamais accepté tel quel depuis le formulaire :
     * un total envoyé par la page est un total qu'on ne peut pas défendre.
     */
    public function updateOrder(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status'   => ['required', 'string', Rule::in(OrderStatus::ALL)],
            'shipping' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'reply'    => ['nullable', 'string', 'max:255'],
        ]);

        $order = ShopOrder::allTenants()->findOrFail($id);

        $transport = $data['shipping'] === null ? (float) $order->shipping : (float) $data['shipping'];

        $champs = [
            'status'   => $data['status'],
            'shipping' => round($transport, 2),
            // Recalculé, jamais repris du formulaire.
            'total'    => round((float) $order->subtotal + $transport, 2),
            'reply'    => $data['reply'] ?? $order->reply,
        ];

        // Les dates d'étape sont posées UNE FOIS, à la première entrée dans
        // l'étape. Les réécrire à chaque enregistrement effacerait le moment où
        // la chose s'est réellement passée — c'est-à-dire la seule information
        // qui permette de dire, plus tard, si le délai promis a été tenu.
        foreach ([
            OrderStatus::CONFIRMED => 'confirmed_at',
            OrderStatus::SHIPPED   => 'shipped_at',
            OrderStatus::COMPLETED => 'delivered_at',
        ] as $statut => $colonne) {
            if ($data['status'] === $statut && $order->{$colonne} === null) {
                $champs[$colonne] = now();
            }
        }

        $order->forceFill($champs)->save();

        app(AuditService::class)->log('shop.order_updated', null,
            $order->reference.' → '.OrderStatus::label($data['status']));

        return back()->with('success', __('Commande mise à jour.'));
    }

    /* ---------------- interne ---------------- */

    /** @return array<string,mixed> */
    private function reglesArticle(Request $request, ?int $sauf = null): array
    {
        $data = $request->validate([
            'sku'            => ['required', 'string', 'max:40',
                Rule::unique('tagtoa_shop_items', 'sku')->ignore($sauf)],
            'name'           => ['required', 'string', 'max:120'],
            'description'    => ['nullable', 'string', 'max:255'],
            'unit_price'     => ['required', 'numeric', 'min:0', 'max:9999999'],
            // Le minimum et le pas décrivent ce qu'on sait RÉELLEMENT expédier.
            'min_qty'        => ['nullable', 'integer', 'min:1', 'max:100000'],
            'step_qty'       => ['nullable', 'integer', 'min:1', 'max:100000'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'image'          => ['nullable', 'image', 'max:2048'],
        ]);

        $out = [
            'sku'            => $data['sku'],
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'unit_price'     => (float) $data['unit_price'],
            'min_qty'        => max(1, (int) ($data['min_qty'] ?? 1)),
            'step_qty'       => max(1, (int) ($data['step_qty'] ?? 1)),
            'lead_time_days' => $data['lead_time_days'] ?? null,
        ];

        if ($request->hasFile('image')) {
            $out['image_path'] = $request->file('image')->store('tagtoa/shop', 'public');
        }

        return $out;
    }
}
