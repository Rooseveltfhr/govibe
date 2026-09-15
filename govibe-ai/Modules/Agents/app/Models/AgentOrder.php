<?php

namespace Modules\Agents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Yon kòmand: yon biznis ki mande yon ajan.
 *
 * @property int $id
 * @property string $reference
 * @property string $sector
 * @property string $business_name
 * @property string|null $contact_name
 * @property string $whatsapp
 * @property string|null $email
 * @property string $mode
 * @property list<string>|null $channels
 * @property string|null $notes
 * @property string $status
 */
class AgentOrder extends Model
{
    public const MODE_EXPERT = 'expert';

    public const MODE_SELF = 'self';

    public const CHANNELS = ['whatsapp', 'website', 'phone'];

    /** Etap yon dosye. Lòd la se lòd travay la, se pa yon detay. */
    public const STATUSES = ['nouvo', 'an_kou', 'fèt', 'anile'];

    protected $fillable = [
        'reference', 'sector', 'business_name', 'contact_name', 'whatsapp',
        'email', 'mode', 'channels', 'notes', 'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['channels' => 'array'];
    }

    /**
     * Referans lan: LV-AAAAMMJJ-XXXX.
     *
     * Karaktè ki twonpe (0/O, 1/I) retire: moun nan ap li referans sa a nan
     * yon mesaj WhatsApp, pafwa alavwa. Yon « O » ki vin yon « 0 » ta vle di
     * yon dosye nou pa jwenn.
     */
    public static function newReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $suffix = '';

        for ($i = 0; $i < 4; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'LV-'.now()->format('Ymd').'-'.$suffix;
    }

    /** @return HasMany<AgentPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(AgentPayment::class, 'agent_order_id');
    }

    /**
     * Total ki peye, pa deviz.
     *
     * Pa deviz epi pa yon sèl chif: yon avans an goud ak yon rès an dola pa
     * s ajoute. Yon total ki melanje de deviz se yon chif ki bay manti.
     *
     * @return array<string, int> deviz => total an inite minè
     */
    public function paidByCurrency(): array
    {
        $totals = [];

        foreach ($this->payments as $payment) {
            $totals[$payment->currency] = ($totals[$payment->currency] ?? 0) + $payment->amount_minor;
        }

        ksort($totals);

        return $totals;
    }

    public function isPaid(): bool
    {
        return $this->payments()->exists();
    }

    /** Nimewo a an chif sèlman, pou yon lyen wa.me. */
    public function whatsappDigits(): string
    {
        return (string) preg_replace('/\D+/', '', $this->whatsapp);
    }

    public function wantsExpert(): bool
    {
        return $this->mode === self::MODE_EXPERT;
    }

    public function shortNotes(int $limit = 180): string
    {
        return Str::limit((string) $this->notes, $limit);
    }
}
