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

    /**
     * Cycle de vie d'une commande. `picked_up` n'existe qu'entre « Prête » et
     * « Terminée » — le temps qu'un livreur récupère une commande LIVRAISON
     * avant de la livrer ; sur place/à emporter la sautent tout simplement,
     * ready → completed comme avant (voir DashboardController::counterComplete()).
     */
    public const STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'picked_up', 'completed', 'cancelled'];

    public const STATUS_META = [
        'pending'    => ['label' => 'En attente',  'pill' => 'a'],
        'confirmed'  => ['label' => 'Confirmée',   'pill' => 'g'],
        'preparing'  => ['label' => 'En préparation', 'pill' => 'a'],
        'ready'      => ['label' => 'Prête',       'pill' => 'g'],
        'picked_up'  => ['label' => 'Récupérée par le livreur', 'pill' => 'g'],
        'completed'  => ['label' => 'Terminée',    'pill' => 'g'],
        'cancelled'  => ['label' => 'Annulée',     'pill' => 'r'],
    ];

    /** Commandes TRAITÉES : plus rien à faire dessus (liste compacte du tableau de bord). */
    public const DONE_STATUSES = ['completed', 'cancelled'];

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
        'menu_id', 'tenant_id', 'reference', 'order_no', 'order_day', 'subtotal', 'total', 'tip', 'currency',
        'status', 'payment_status', 'payment_method', 'paid_at', 'paid_by', 'channel', 'order_type', 'customer_name', 'customer_phone',
        'table_label', 'delivery_address', 'delivery_reference', 'delivery_zone_label', 'note', 'client_uuid', 'placed_at',
        'tax_total', 'tax_base', 'tax_inclusive', 'tax_label', 'tax_breakdown', 'delivery_fee',
        'courier_id', 'courier_assigned_at', 'picked_up_at', 'hotel_room_id',
        'payment_claim', 'payment_claimed_at',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'total'         => 'decimal:2',
        'tip'           => 'decimal:2',
        'delivery_fee'  => 'decimal:2',
        'placed_at'     => 'datetime',
        'payment_claim' => 'array',
        'payment_claimed_at' => 'datetime',
        'paid_at'       => 'datetime',
        // Copiés sur la commande : changer le réglage du commerce ne doit
        // jamais retourner le sens d'une commande déjà passée.
        'tax_total'     => 'decimal:2',
        'tax_base'      => 'decimal:2',
        'tax_inclusive' => 'boolean',
        'tax_breakdown' => 'array',
        'courier_assigned_at' => 'datetime',
        'picked_up_at'        => 'datetime',
        'order_day'           => 'date:Y-m-d',
    ];

    /** Toujours « Y-m-d » en base, quel que soit ce qu'on passe (Carbon, texte) : l'index unique compare ce texte sur SQLite comme sur MySQL. */
    public function setOrderDayAttribute($value): void
    {
        $this->attributes['order_day'] = $value ? \Carbon\Carbon::parse($value)->toDateString() : null;
    }

    /** Numéro court affiché : « #042 » (null pour une commande d'avant la numérotation : on retombe alors sur la référence). */
    public function getShortNoAttribute(): ?string
    {
        return $this->order_no ? '#'.str_pad((string) $this->order_no, 3, '0', STR_PAD_LEFT) : null;
    }

    /** Heure locale à partir de laquelle un « jour de service » commence : un service de nuit (jusqu'à 3 h) reste dans la journée de la veille. */
    public const SERVICE_DAY_STARTS_AT = 4;

    /** Le jour de service d'un menu, à l'heure LOCALE du menu (pas UTC : sinon la numérotation repartirait à 19 h en plein dîner). */
    public static function serviceDay(?string $timezone = null): string
    {
        return now($timezone ?: 'America/Port-au-Prince')->subHours(self::SERVICE_DAY_STARTS_AT)->toDateString();
    }

    /**
     * Prochain numéro court de CE menu pour le jour de service en cours. À appeler DANS la transaction qui crée la commande.
     *
     * Sous MySQL (InnoDB, REPEATABLE READ) une simple lecture MAX() verrait l'instantané ouvert par les lectures précédentes de la
     * transaction — pas la commande que l'autre transaction vient de valider — et les deux prendraient le même numéro. Donc :
     * (1) on verrouille la ligne du menu (les commandes d'un menu passent une par une) ET (2) on lit le max en LECTURE VERROUILLANTE,
     * qui voit la dernière version validée. L'index unique reste le dernier filet ; placeOrder réessaie si malgré tout il se déclenche.
     *
     * @return array{0:string,1:int}  [jour (Y-m-d), numéro]
     */
    public static function nextNumber(Menu|int $menu): array
    {
        $menuId = $menu instanceof Menu ? $menu->id : $menu;
        $tz = $menu instanceof Menu ? $menu->timezone : \Modules\Tagtoa\App\Models\Menu\Menu::whereKey($menuId)->value('timezone');
        $jour = self::serviceDay($tz);

        \Modules\Tagtoa\App\Models\Menu\Menu::whereKey($menuId)->lockForUpdate()->value('id');
        $dernier = (int) static::query()->where('menu_id', $menuId)->where('order_day', $jour)->lockForUpdate()->max('order_no');

        return [$jour, $dernier + 1];
    }

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

    public function courier(): BelongsTo
    {
        return $this->belongsTo(\Modules\Tagtoa\App\Models\Staff\Staff::class, 'courier_id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Les moyens qu'un encaissement à la main peut déclarer. Les passerelles
     * (moncash, paypal, stripe…) s'ajoutent d'elles-mêmes via markPaid(), mais
     * ne se choisissent jamais dans la liste du caissier.
     */
    public const MANUAL_PAYMENT_METHODS = [
        'cash'     => 'Espèces',
        'moncash'  => 'MonCash',
        'natcash'  => 'NatCash',
        'card'     => 'Carte',
        'transfer' => 'Virement',
        'other'    => 'Autre',
    ];

    public function getPaymentMethodLabelAttribute(): ?string
    {
        if (! $this->payment_method) {
            return null;
        }

        return self::MANUAL_PAYMENT_METHODS[$this->payment_method] ?? ucfirst($this->payment_method);
    }

    /** Au-delà, une commande encore à traiter passe en rouge : en restaurant, quinze minutes sans réponse est déjà trop. */
    public const LATE_AFTER_MINUTES = 15;

    /**
     * L'UNE action principale de la carte : l'étape suivante du cycle (Confirmer → Cuisine → Prête → Remise).
     * Annuler et corriger vivent dans le menu « ⋯ » — jamais à côté du geste de tous les jours. null = rien à faire (terminée, annulée).
     *
     * @return array{status:string,label:string,icon:string}|null
     */
    public function nextStep(): ?array
    {
        return match ($this->status) {
            'pending'   => ['status' => 'confirmed', 'label' => 'Confirmer',          'icon' => 'fa-check'],
            'confirmed' => ['status' => 'preparing', 'label' => 'Envoyer en cuisine', 'icon' => 'fa-fire-burner'],
            'preparing' => ['status' => 'ready',     'label' => 'Marquer prête',      'icon' => 'fa-bell-concierge'],
            // « Prête → Récupérée » : jamais sans livreur (même règle que l'écran Livraison). null → la carte propose d'en assigner un.
            'ready'     => $this->order_type === 'delivery'
                ? ($this->courier_id ? ['status' => 'picked_up', 'label' => 'Récupérée par le livreur', 'icon' => 'fa-motorcycle'] : null)
                : $this->finalStep($this->order_type === 'pickup' ? 'Remise au client' : 'Servie'),
            'picked_up' => $this->finalStep('Livrée'),
            default     => null,
        };
    }

    /**
     * Terminer une commande ne vaut JAMAIS « non payée » : la terminer la fait sortir de la liste active, des compteurs et du filtre
     * « À encaisser », et le revenu ne serait plus jamais enregistré. L'écran Comptoir/Livraison encaissent en terminant ; ici
     * l'encaissement est un geste explicite (pastilles) — donc l'étape finale n'est offerte qu'une fois la commande payée.
     */
    private function finalStep(string $label): ?array
    {
        return $this->isPaid() ? ['status' => 'completed', 'label' => $label, 'icon' => 'fa-flag-checkered'] : null;
    }

    /** La carte attend un encaissement avant de pouvoir se terminer ? (pour l'indiquer plutôt que de laisser un vide) */
    public function awaitsPaymentToFinish(): bool
    {
        return ! $this->isPaid() && $this->status !== 'cancelled'
            && (in_array($this->status, ['ready', 'picked_up'], true))
            && ! ($this->order_type === 'delivery' && $this->status === 'ready');
    }

    /** Livraison prête à partir mais personne pour la porter. */
    public function awaitsCourier(): bool
    {
        return $this->order_type === 'delivery' && $this->status === 'ready' && ! $this->courier_id;
    }

    /** Minutes écoulées depuis la commande (null si la date manque). */
    public function ageMinutes(): ?int
    {
        return $this->placed_at ? max(0, (int) $this->placed_at->diffInMinutes(\Carbon\Carbon::now(), false)) : null;
    }

    /** Encore à traiter ET en attente depuis plus de LATE_AFTER_MINUTES. */
    public function isLate(): bool
    {
        $age = $this->ageMinutes();

        return $age !== null && ! in_array($this->status, self::DONE_STATUSES, true) && $age > self::LATE_AFTER_MINUTES;
    }

    /** « à l'instant », « il y a 12 min », « il y a 3 h », « il y a 2 j » — traduisible (le nombre est un paramètre, pas dans la clé). */
    public static function ageLabel(int $minutes): string
    {
        return match (true) {
            $minutes < 1    => __("à l'instant"),
            $minutes < 60   => __('il y a :n min', ['n' => $minutes]),
            $minutes < 1440 => __('il y a :n h', ['n' => intdiv($minutes, 60)]),
            default         => __('il y a :n j', ['n' => intdiv($minutes, 1440)]),
        };
    }

    /** Icône et couleur du mode de service : la livraison demande un coursier, ça doit se voir de loin. */
    public const ORDER_TYPE_STYLE = [
        'dine_in'  => ['icon' => 'fa-utensils',   'color' => '#1d4ed8', 'bg' => '#e8efff'],
        'pickup'   => ['icon' => 'fa-bag-shopping', 'color' => '#92400e', 'bg' => '#fff1d6'],
        'delivery' => ['icon' => 'fa-motorcycle', 'color' => '#6b21a8', 'bg' => '#f3e8ff'],
    ];

    public function getStatusMetaAttribute(): array
    {
        return self::STATUS_META[$this->status] ?? ['label' => ucfirst($this->status), 'pill' => 'n'];
    }

    public function getOrderTypeLabelAttribute(): string
    {
        return self::ORDER_TYPE_LABELS[$this->order_type] ?? ucfirst((string) $this->order_type);
    }
}
