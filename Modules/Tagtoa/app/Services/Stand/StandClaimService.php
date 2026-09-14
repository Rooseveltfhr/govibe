<?php

namespace Modules\Tagtoa\App\Services\Stand;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandClaimAttempt;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Support\Stand\StandId;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA SMART STAND — vérifier un code, puis réclamer le stand.
 *
 * Deux temps, volontairement séparés :
 *
 *   1. VÉRIFIER — sans compte. Le code est contrôlé et le stand réservé quinze
 *      minutes. Faire créer un compte à quelqu'un pour lui annoncer ensuite que
 *      son code est illisible est la façon la plus sûre de le perdre.
 *   2. RÉCLAMER — avec un compte. Le code est consommé, le stand devient celui
 *      du commerce.
 *
 * La limitation est PAR STAND, pas seulement par adresse : une limite par IP se
 * contourne avec un réseau de proxys, une limite par stand vaut quel que soit
 * le nombre de machines de l'attaquant.
 */
class StandClaimService
{
    /** Essais autorisés par stand et par heure. */
    public const MAX_ATTEMPTS = 10;

    /** Durée de la réservation. Assez pour créer un compte sans se presser. */
    public const RESERVE_MINUTES = 15;

    public function __construct(
        protected StandMinter $minter,
        protected StandResolver $resolver,
    ) {
    }

    /* Résultats possibles. Une chaîne libre divergerait entre le service et
       l'écran qui l'affiche. */
    public const OK          = 'ok';
    public const BAD_CODE    = 'bad_code';
    public const NOT_FOUND   = 'not_found';
    public const NOT_OPEN    = 'not_open';    // déjà réclamé, perdu, lot rappelé
    public const TOO_MANY    = 'too_many';
    public const RESERVED    = 'reserved';    // réservé par quelqu'un d'autre

    /**
     * Vérifie un code et réserve le stand. Aucun compte requis.
     *
     * @return array{result:string, token:?string, stand:?Stand}
     */
    public function verify(?string $publicId, ?string $secret, array $trace = []): array
    {
        $stand = $this->resolver->stand($publicId);

        // Le verrouillage se fait AVANT la vérification : sinon un attaquant
        // dépense la comparaison bcrypt à chaque essai, et la limite ne protège
        // que la base, pas le processeur.
        if ($stand && $this->tooMany($stand)) {
            $this->trace($stand, $publicId, false, $trace);

            return ['result' => self::TOO_MANY, 'token' => null, 'stand' => null];
        }

        // La vérification est faite MÊME si le stand n'existe pas : c'est elle
        // qui dépense le temps, et un identifiant absent ne doit pas répondre
        // plus vite qu'un identifiant réel.
        $ok = $this->minter->verify($stand, $secret);

        $this->trace($stand, $publicId, $ok, $trace);

        if (! $stand) {
            return ['result' => self::NOT_FOUND, 'token' => null, 'stand' => null];
        }

        if (! $stand->isClaimable()) {
            return ['result' => self::NOT_OPEN, 'token' => null, 'stand' => null];
        }

        if (! $ok) {
            return ['result' => self::BAD_CODE, 'token' => null, 'stand' => null];
        }

        // Réservé par quelqu'un d'autre, et la réservation court toujours.
        if ($this->reservedByOther($stand)) {
            return ['result' => self::RESERVED, 'token' => null, 'stand' => null];
        }

        $token = Str::random(48);

        $stand->forceFill([
            'digital_state'        => StandState::CLAIM_PENDING,
            'claim_reserved_until' => now()->addMinutes(self::RESERVE_MINUTES),
            'claim_reserved_token' => hash('sha256', $token),
        ])->save();

        $this->resolver->forget($stand->public_id);

        return ['result' => self::OK, 'token' => $token, 'stand' => $stand];
    }

    /**
     * Consomme la réservation : le stand devient celui du commerce.
     *
     * Verrou de ligne et re-contrôle DANS la transaction : deux personnes qui
     * grattent le même carton et valident en même temps ne doivent pas
     * réclamer toutes les deux. Sans le verrou, les deux lisent « libre » puis
     * écrivent tour à tour, et la seconde écrase la première en silence.
     *
     * @return array{result:string, stand:?Stand}
     */
    public function claim(?string $publicId, ?string $token, ?string $tenantId, array $context = []): array
    {
        $id = StandId::normalizeId($publicId);

        if (! StandId::isValidId($id) || ! $token || ! $tenantId) {
            return ['result' => self::NOT_FOUND, 'stand' => null];
        }

        $resultat = DB::transaction(function () use ($id, $token, $tenantId, $context) {
            $stand = Stand::with('batch')->byPublicId($id)->lockForUpdate()->first();

            if (! $stand) {
                return ['result' => self::NOT_FOUND, 'stand' => null];
            }

            // Déjà réclamé entre la vérification et maintenant.
            if ($stand->digital_state !== StandState::CLAIM_PENDING || ! $stand->isClaimable()) {
                return ['result' => self::NOT_OPEN, 'stand' => null];
            }

            $expiree = $stand->claim_reserved_until === null
                || now()->greaterThan($stand->claim_reserved_until);

            // Comparaison en temps constant : le jeton vaut le droit de
            // réclamer, il se compare comme un secret.
            $bon = $stand->claim_reserved_token !== null
                && hash_equals($stand->claim_reserved_token, hash('sha256', $token));

            if ($expiree || ! $bon) {
                return ['result' => self::NOT_OPEN, 'stand' => null];
            }

            $stand->forceFill([
                'tenant_id'            => $tenantId,
                'digital_state'        => StandState::ACTIVE,
                'physical_state'       => StandState::SOLD,
                'holder_type'          => 'business',
                'holder_id'            => null,
                'target_module'        => $context['target_module'] ?? 'menu',
                'location_label'       => $context['location_label'] ?? null,
                'claimed_at'           => now(),
                // La réservation est consommée : le jeton ne peut pas resservir.
                'claim_reserved_until' => null,
                'claim_reserved_token' => null,
            ])->save();

            StandEvent::create([
                'stand_id'   => $stand->id,
                'event'      => StandEvent::CLAIMED,
                'from_state' => StandState::CLAIM_PENDING,
                'to_state'   => StandState::ACTIVE,
                'actor_type' => 'business',
                'actor_name' => $context['actor_name'] ?? null,
                'tenant_id'  => $tenantId,
                'ip'         => $context['ip'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
            ]);

            return ['result' => self::OK, 'stand' => $stand];
        });

        if ($resultat['result'] === self::OK) {
            $this->resolver->forget($id);
        }

        return $resultat;
    }

    /**
     * Libère une réservation expirée.
     *
     * Un client qui abandonne à mi-parcours ne doit pas condamner un objet
     * qu'il a payé : le stand redevient réclamable de lui-même.
     */
    public function releaseExpired(Stand $stand): bool
    {
        if ($stand->digital_state !== StandState::CLAIM_PENDING) {
            return false;
        }

        if ($stand->claim_reserved_until && now()->lessThan($stand->claim_reserved_until)) {
            return false;
        }

        $stand->forceFill([
            'digital_state'        => StandState::UNCLAIMED,
            'claim_reserved_until' => null,
            'claim_reserved_token' => null,
        ])->save();

        $this->resolver->forget($stand->public_id);

        return true;
    }

    /* ---------------- interne ---------------- */

    /** Trop d'essais sur CE stand dans l'heure ? */
    private function tooMany(Stand $stand): bool
    {
        return StandClaimAttempt::where('stand_id', $stand->id)
            ->where('succeeded', false)
            ->where('created_at', '>=', now()->subHour())
            ->count() >= self::MAX_ATTEMPTS;
    }

    /** Réservé par quelqu'un d'autre, réservation encore valable ? */
    private function reservedByOther(Stand $stand): bool
    {
        return $stand->digital_state === StandState::CLAIM_PENDING
            && $stand->claim_reserved_until !== null
            && now()->lessThan($stand->claim_reserved_until);
    }

    /** Journalise la tentative. Jamais le code essayé. */
    private function trace(?Stand $stand, ?string $publicId, bool $ok, array $trace): void
    {
        StandClaimAttempt::create([
            'stand_id'        => $stand?->id,
            'public_id_tried' => mb_substr(StandId::normalizeId($publicId), 0, 24),
            'succeeded'       => $ok,
            'ip'              => $trace['ip'] ?? null,
            'user_agent'      => mb_substr((string) ($trace['user_agent'] ?? ''), 0, 255) ?: null,
        ]);
    }
}
