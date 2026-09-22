<?php

namespace Modules\Tagtoa\App\Models\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA MENU — commande client (capturée en base).
 */
class Order extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_menu_orders';

    /** Cycle de vie d'une commande. */
    public const STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'];

    public const STATUS_META = [
        'pending'   => ['label' => 'En attente',  'pill' => 'a'],
        'confirmed' => ['label' => 'Confirmée',   'pill' => 'g'],
        'preparing' => ['label' => 'En préparation', 'pill' => 'a'],
        'ready'     => ['label' => 'Prête',       'pill' => 'g'],
        'completed' => ['label' => 'Terminée',    'pill' => 'g'],
        'cancelled' => ['label' => 'Annulée',     'pill' => 'r'],
    ];

    /** Statuts qu'une cuisine doit encore traiter — avant « Prête ». */
    public const KITCHEN_STATUSES = ['pending', 'confirmed', 'preparing'];

    /** Mode de service. */
    public const ORDER_TYPES = ['dine_in', 'pickup', 'delivery'];

    public const ORDER_TYPE_LABELS = [
        'dine_in'  => 'Sur place',
        'pickup'   => 'À emporter',
        'delivery' => 'Livraison',
    ];

    /**
     * Nettoie les modes de service qu'un menu déclare offrir : ne garde que
     * des codes valides, et retombe sur null (« pas de restriction ») si le
     * résultat est vide ou couvre déjà les trois modes — même convention que
     * Locale::sanitizeSelection()/BusinessHours::sanitize() : null, jamais
     * une liste vide qui empêcherait toute commande. PUR.
     */
    public static function sanitizeServiceTypes(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $retenus = array_values(array_intersect(self::ORDER_TYPES, $input));
        sort($retenus);
        $tous = self::ORDER_TYPES;
        sort($tous);

        return ($retenus === [] || $retenus === $tous) ? null : $retenus;
    }

    /** Les modes de service qu'un menu offre réellement — sa sélection, ou tous si aucune restriction. */
    public static function serviceTypesFor(?array $types): array
    {
        return $types ?: self::ORDER_TYPES;
    }

    protected $fillable = [
        'menu_id', 'tenant_id', 'reference', 'subtotal', 'total', 'tip', 'currency',
        'status', 'payment_status', 'channel', 'order_type', 'customer_name', 'customer_phone',
        'table_label', 'delivery_address', 'delivery_zone_label', 'note', 'client_uuid', 'placed_at',
        'tax_total', 'tax_base', 'tax_inclusive', 'tax_label', 'tax_breakdown', 'delivery_fee',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'total'         => 'decimal:2',
        'tip'           => 'decimal:2',
        'delivery_fee'  => 'decimal:2',
        'placed_at'     => 'datetime',
        // Copiés sur la commande : changer le réglage du commerce ne doit
        // jamais retourner le sens d'une commande déjà passée.
        'tax_total'     => 'decimal:2',
        'tax_base'      => 'decimal:2',
        'tax_inclusive' => 'boolean',
        'tax_breakdown' => 'array',
    ];

    public static function generateReference(): string
    {
        return 'CMD-'.strtoupper(Str::random(8));
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function getStatusMetaAttribute(): array
    {
        return self::STATUS_META[$this->status] ?? ['label' => ucfirst($this->status), 'pill' => 'n'];
    }

    public function getOrderTypeLabelAttribute(): string
    {
        return self::ORDER_TYPE_LABELS[$this->order_type] ?? ucfirst((string) $this->order_type);
    }
}
