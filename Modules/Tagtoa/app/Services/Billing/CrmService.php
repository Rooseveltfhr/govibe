<?php

namespace Modules\Tagtoa\App\Services\Billing;

use Modules\Tagtoa\App\Models\Event\Order as EventOrder;
use Modules\Tagtoa\App\Models\Loyalty\Card;
use Modules\Tagtoa\App\Models\Menu\Order as MenuOrder;
use Modules\Tagtoa\App\Models\Pay\PaymentProof;
use Modules\Tagtoa\App\Models\Pos\Sale as PosSale;

/**
 * TAGTOA — CRM léger : agrège les clients depuis les sources existantes
 * (commandes menu, événements, preuves de paiement, ventes POS, fidélité),
 * dédoublonnés par téléphone (sinon nom). Aucune table dédiée (v1 lecture).
 */
class CrmService
{
    /** Nb max de lignes lues par source (borne raisonnable). */
    public const CAP = 1000;

    public function __construct(protected ?string $tenantId)
    {
    }

    private function safe(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Liste agrégée des clients, triée par dernière activité. */
    public function customers(): array
    {
        $acc = [];
        $push = function (?string $name, ?string $phone, ?string $email, $amount, $date, string $channel) use (&$acc) {
            $digits = $phone ? preg_replace('/\D+/', '', $phone) : '';
            $key = $digits ?: ($name ? 'n:'.mb_strtolower(trim($name)) : null);
            if (! $key) {
                return;
            }
            if (! isset($acc[$key])) {
                $acc[$key] = ['name' => $name, 'phone' => $phone, 'email' => $email, 'orders' => 0, 'spent' => 0.0, 'last' => null, 'channels' => []];
            }
            $c = &$acc[$key];
            $c['name'] = $c['name'] ?: $name;
            $c['phone'] = $c['phone'] ?: $phone;
            $c['email'] = $c['email'] ?: $email;
            $c['orders']++;
            $c['spent'] += (float) $amount;
            $c['channels'][$channel] = true;
            $ts = $date ? strtotime((string) $date) : 0;
            if ($ts && (! $c['last'] || $ts > $c['last'])) {
                $c['last'] = $ts;
            }
        };

        $tid = $this->tenantId;

        foreach ($this->safe(fn () => MenuOrder::where('tenant_id', $tid)->latest()->limit(self::CAP)->get()) as $o) {
            $push($o->customer_name, $o->customer_phone, null, $o->total, $o->created_at, 'menu');
        }
        foreach ($this->safe(fn () => EventOrder::whereHas('event', fn ($q) => $q->where('tenant_id', $tid))->latest()->limit(self::CAP)->get()) as $o) {
            $push($o->buyer_name, $o->buyer_phone, $o->buyer_email, $o->total, $o->created_at, 'event');
        }
        foreach ($this->safe(fn () => PaymentProof::whereHas('page', fn ($q) => $q->where('tenant_id', $tid))->latest()->limit(self::CAP)->get()) as $p) {
            $push($p->payer_name, $p->payer_phone, null, $p->amount, $p->created_at, 'pay');
        }
        foreach ($this->safe(fn () => PosSale::whereHas('terminal', fn ($q) => $q->where('tenant_id', $tid))->latest()->limit(self::CAP)->get()) as $s) {
            $push(null, $s->customer_phone, null, $s->total, $s->created_at, 'pos');
        }
        foreach ($this->safe(fn () => Card::whereHas('program', fn ($q) => $q->where('tenant_id', $tid))->latest()->limit(self::CAP)->get()) as $c) {
            $push($c->cardholder_name, $c->cardholder_phone, $c->cardholder_email, 0, $c->created_at, 'loyalty');
        }

        usort($acc, fn ($a, $b) => ($b['last'] ?? 0) <=> ($a['last'] ?? 0));

        return array_values($acc);
    }
}
