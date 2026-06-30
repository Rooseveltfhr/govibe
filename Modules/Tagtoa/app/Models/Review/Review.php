<?php

namespace Modules\Tagtoa\App\Models\Review;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA REVIEWS — avis client attaché à une ressource publique.
 */
class Review extends Model
{
    protected $table = 'tagtoa_reviews';

    public const STATUSES = ['pending', 'approved', 'rejected'];

    public const STATUS_META = [
        'pending'  => ['label' => 'En attente', 'pill' => 'a'],
        'approved' => ['label' => 'Publié',     'pill' => 'g'],
        'rejected' => ['label' => 'Rejeté',     'pill' => 'r'],
    ];

    /** Ressources pouvant recevoir des avis : type => [label, modèle]. */
    public const SUBJECTS = [
        'menu'    => ['label' => 'Menu',         'model' => \Modules\Tagtoa\App\Models\Menu\Menu::class],
        'booking' => ['label' => 'Réservations', 'model' => \Modules\Tagtoa\App\Models\Booking\BookingPage::class],
        'site'    => ['label' => 'Site web',     'model' => \Modules\Tagtoa\App\Models\Site\Site::class],
        'event'   => ['label' => 'Événements',   'model' => \Modules\Tagtoa\App\Models\Event\Event::class],
    ];

    protected $fillable = [
        'tenant_id', 'subject_type', 'subject_id', 'subject_alias', 'rating',
        'author_name', 'author_phone', 'author_email', 'comment', 'status',
        'is_featured', 'reply', 'replied_at', 'client_uuid',
    ];

    protected $casts = [
        'rating'      => 'integer',
        'is_featured' => 'boolean',
        'replied_at'  => 'datetime',
    ];

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function scopeForSubject(Builder $q, string $type, int $id): Builder
    {
        return $q->where('subject_type', $type)->where('subject_id', $id);
    }

    public function getStatusMetaAttribute(): array
    {
        return self::STATUS_META[$this->status] ?? ['label' => ucfirst($this->status), 'pill' => 'n'];
    }

    /** Note bornée 1..5 (sécurité d'affichage). */
    public function getStarsAttribute(): int
    {
        return max(1, min(5, (int) $this->rating));
    }
}
