<?php

namespace Modules\Tagtoa\App\Services\Billing;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Billing\Commission;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Order as EventOrder;
use Modules\Tagtoa\App\Models\Links\LinkPage;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order as MenuOrder;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pay\PaymentProof;
use Modules\Tagtoa\App\Models\Pos\Sale as PosSale;
use Modules\Tagtoa\App\Models\Site\Site;

/**
 * TAGTOA — analytics marchand (tolérant : chaque calcul isolé en try/catch).
 */
class AnalyticsService
{
    public function __construct(protected ?string $tenantId)
    {
    }

    private function safe(callable $fn, $default = 0)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /** Revenu encaissé par devise (commandes payées + ventes + preuves approuvées). */
    public function revenueByCurrency(): array
    {
        $out = [];
        $add = function ($rows) use (&$out) {
            foreach ($rows as $r) {
                $cur = $r->currency ?: 'HTG';
                $out[$cur] = ($out[$cur] ?? 0) + (float) $r->total;
            }
        };

        $add($this->safe(fn () => MenuOrder::where('tenant_id', $this->tenantId)->where('payment_status', 'paid')
            ->selectRaw('currency, SUM(total) total')->groupBy('currency')->get(), collect()));
        $add($this->safe(fn () => PosSale::whereHas('terminal', fn ($q) => $q->where('tenant_id', $this->tenantId))->where('status', 1)
            ->selectRaw('currency, SUM(total) total')->groupBy('currency')->get(), collect()));
        $add($this->safe(fn () => EventOrder::whereHas('event', fn ($q) => $q->where('tenant_id', $this->tenantId))->whereNotNull('paid_at')
            ->selectRaw('currency, SUM(total) total')->groupBy('currency')->get(), collect()));
        $add($this->safe(fn () => PaymentProof::whereHas('page', fn ($q) => $q->where('tenant_id', $this->tenantId))->where('status', PaymentProof::STATUS_APPROVED)
            ->selectRaw('currency, SUM(amount) total')->groupBy('currency')->get(), collect()));

        return $out;
    }

    public function counts(): array
    {
        $tid = $this->tenantId;

        return [
            'orders' => $this->safe(fn () => MenuOrder::where('tenant_id', $tid)->count())
                + $this->safe(fn () => PosSale::whereHas('terminal', fn ($q) => $q->where('tenant_id', $tid))->count())
                + $this->safe(fn () => EventOrder::whereHas('event', fn ($q) => $q->where('tenant_id', $tid))->count()),
            'views' => $this->safe(fn () => (int) Site::where('tenant_id', $tid)->sum('views'))
                + $this->safe(fn () => (int) Menu::where('tenant_id', $tid)->sum('views'))
                + $this->safe(fn () => (int) PaymentPage::where('tenant_id', $tid)->sum('views'))
                + $this->safe(fn () => (int) LinkPage::where('tenant_id', $tid)->sum('views'))
                + $this->safe(fn () => (int) Event::where('tenant_id', $tid)->sum('views')),
            'commissions' => $this->safe(fn () => (float) Commission::where('tenant_id', $tid)->sum('commission_amount')),
            'pending' => $this->safe(fn () => MenuOrder::where('tenant_id', $tid)->where('status', 'pending')->count()),
        ];
    }

    /** Ventilation du nombre de commandes par module. */
    public function ordersByModule(): array
    {
        $tid = $this->tenantId;

        return [
            'menu'  => $this->safe(fn () => MenuOrder::where('tenant_id', $tid)->count()),
            'pos'   => $this->safe(fn () => PosSale::whereHas('terminal', fn ($q) => $q->where('tenant_id', $tid))->count()),
            'event' => $this->safe(fn () => EventOrder::whereHas('event', fn ($q) => $q->where('tenant_id', $tid))->count()),
            'pay'   => $this->safe(fn () => PaymentProof::whereHas('page', fn ($q) => $q->where('tenant_id', $tid))->count()),
        ];
    }

    /** Revenu MENU + POS par jour sur N jours (pour mini-graphe). */
    public function dailyRevenue(int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $series[now()->subDays($days - 1 - $i)->format('Y-m-d')] = 0.0;
        }

        $menu = $this->safe(fn () => MenuOrder::where('tenant_id', $this->tenantId)->where('payment_status', 'paid')
            ->where('created_at', '>=', $start)->selectRaw('DATE(created_at) d, SUM(total) t')->groupBy('d')->pluck('t', 'd'), collect());
        $pos = $this->safe(fn () => PosSale::whereHas('terminal', fn ($q) => $q->where('tenant_id', $this->tenantId))->where('status', 1)
            ->where('created_at', '>=', $start)->selectRaw('DATE(created_at) d, SUM(total) t')->groupBy('d')->pluck('t', 'd'), collect());

        foreach ($series as $d => $_) {
            $series[$d] = (float) ($menu[$d] ?? 0) + (float) ($pos[$d] ?? 0);
        }

        return $series;
    }

    /** Top produits MENU (par quantité vendue). */
    public function topMenuItems(int $limit = 5): array
    {
        return $this->safe(function () use ($limit) {
            return DB::table('tagtoa_menu_order_items as i')
                ->join('tagtoa_menu_orders as o', 'o.id', '=', 'i.order_id')
                ->where('o.tenant_id', $this->tenantId)
                ->selectRaw('i.name, SUM(i.qty) qty, SUM(i.line_total) total')
                ->groupBy('i.name')->orderByDesc('qty')->limit($limit)->get()->toArray();
        }, []);
    }
}
