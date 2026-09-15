<?php

namespace Modules\Agents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Support\HaitianCurrency;

/**
 * Yon peman sou yon kòmand.
 *
 * Montan an an inite minè (santim) — zewo flotan nan lajan.
 *
 * @property int $agent_order_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $method
 * @property string|null $reference
 */
class AgentPayment extends Model
{
    /** Metòd ki mache toutbon isit la. « lot » kouvri rès la. */
    public const METHODS = ['moncash', 'natcash', 'unibank', 'sogebank', 'kach', 'lot'];

    protected $fillable = [
        'agent_order_id', 'amount_minor', 'currency', 'method', 'reference', 'received_at', 'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'received_at' => 'date',
        ];
    }

    /** @return BelongsTo<AgentOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(AgentOrder::class, 'agent_order_id');
    }

    public function formatted(): string
    {
        $major = intdiv($this->amount_minor, 100);

        return $this->currency === 'HTG'
            ? HaitianCurrency::formatGourdes($major)
            : number_format($this->amount_minor / 100, 2).' '.$this->currency;
    }
}
