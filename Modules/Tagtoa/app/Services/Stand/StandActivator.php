<?php

namespace Modules\Tagtoa\App\Services\Stand;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Support\Stand\StandScratch;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA SMART STAND — quarante tables, quatre minutes.
 *
 * ── Le problème que ce service résout ────────────────────────────────────
 *
 * Le parcours de M3 fait deux temps : VÉRIFIER sans compte, puis RÉCLAMER avec
 * un compte. Cette séparation est juste — mais elle n'est juste QUE pour le
 * premier stand. Elle existe parce qu'il ne faut pas faire créer un compte à
 * quelqu'un pour lui annoncer ensuite que son code est illisible.
 *
 * Au stand numéro deux, le compte existe déjà. Le marchand est connecté, son
 * commerce est choisi, et lui redemander de repasser par une page publique, une
 * réservation de quinze minutes et un jeton de session est du cérémonial pur.
 * Répété trente-neuf fois, c'est ce cérémonial — pas la sécurité — qui fait que
 * les stands restent dans le carton.
 *
 * Ici, donc : UN geste. On lit, on vérifie, on réclame, dans une seule
 * transaction.
 *
 * ── Ce qui n'est PAS assoupli ────────────────────────────────────────────
 *
 * Le secret reste exigé, entier, à chaque stand. Le QR du recto est
 * photographiable — c'est acquis depuis M2 — donc une activation qui se
 * contenterait de l'identifiant laisserait n'importe quel passant réclamer les
 * stands d'un restaurant depuis le trottoir. Ce qui change est la façon de
 * SAISIR le secret (une visée au lieu de huit caractères tapés), pas le fait de
 * le prouver.
 *
 * Le compteur d'essais est le MÊME que celui de M3 : deux parcours, une seule
 * serrure. S'il y en avait deux, on forcerait les codes par la porte la moins
 * surveillée.
 *
 * ── Ce que ce service ne fait délibérément pas ───────────────────────────
 *
 * Il ne réclame pas une PLAGE. Offrir « ce stand appartient au carton 41–80,
 * veux-tu les quarante ? » transformerait un seul secret volé — celui d'un
 * stand posé sur une table — en quarante stands réclamés. Le gain serait de
 * trois minutes ; le coût, la seule preuve de possession dont dispose le
 * modèle. Scanner reste rapide et reste honnête.
 */
class StandActivator
{
    /* Résultats. Une chaîne libre divergerait entre le service et l'écran. */
    public const OK           = 'ok';
    public const ALREADY_MINE = 'already_mine'; // rescanné par mégarde
    public const NO_SECRET    = 'no_secret';    // le recto seul a été visé
    public const UNREADABLE   = 'unreadable';   // ni identifiant ni secret
    public const NOT_FOUND    = 'not_found';
    public const BAD_CODE     = 'bad_code';
    public const NOT_OPEN     = 'not_open';     // à quelqu'un d'autre, perdu, rappelé
    public const TOO_MANY     = 'too_many';
    public const NO_BUSINESS  = 'no_business';

    /** Préfixe proposé par défaut : c'est le cas de très loin le plus fréquent. */
    public const DEFAULT_PREFIX = 'Table';

    public function __construct(
        protected StandMinter $minter,
        protected StandResolver $resolver,
        protected StandClaimService $claims,
    ) {
    }

    /**
     * Lit une charge utile grattée et rattache le stand au commerce.
     *
     * @param  string|null  $lecture  « TG-000041-A3F9K2MP », telle que lue
     * @return array{result:string, stand:?Stand, label:?string}
     */
    public function activate(?string $lecture, ?string $tenantId, array $context = []): array
    {
        if (! $tenantId) {
            return $this->non(self::NO_BUSINESS);
        }

        [$publicId, $secret] = StandScratch::parse($lecture);

        if ($publicId === null) {
            // Une caméra lit tout ce qui passe : un code produit, une étiquette
            // de transporteur. Ce n'est pas une erreur du marchand.
            return $this->non(self::UNREADABLE);
        }

        if ($secret === null) {
            // Le recto a été visé au lieu du panneau gratté — le geste le plus
            // probable de tous. L'écran doit le dire, pas crier à l'échec.
            return $this->non(self::NO_SECRET);
        }

        $stand = $this->resolver->stand($publicId);

        // Déjà le sien : on ne consomme AUCUN essai. Un marchand qui repasse la
        // caméra sur une table déjà faite est le cas normal quand on avance
        // dans une salle ; le punir en rapprochant la limite serait absurde.
        if ($stand && $stand->tenant_id === $tenantId
            && StandState::resolvesToBusiness($stand->digital_state)) {
            return [
                'result' => self::ALREADY_MINE,
                'stand'  => $stand,
                'label'  => $stand->location_label,
            ];
        }

        // Contrôlé AVANT bcrypt : sinon chaque essai coûte notre processeur.
        if ($stand && $this->claims->tooMany($stand)) {
            $this->claims->traceAttempt($stand, $publicId, false, $context);

            return $this->non(self::TOO_MANY);
        }

        // Vérifié MÊME si le stand n'existe pas : un identifiant absent ne doit
        // pas répondre plus vite qu'un identifiant réel.
        $ok = $this->minter->verify($stand, $secret);

        $this->claims->traceAttempt($stand, $publicId, $ok, $context);

        if (! $stand) {
            return $this->non(self::NOT_FOUND);
        }

        // L'ORDRE compte, et il est le même qu'au parcours public de M3.
        //
        // Un stand perdu, rappelé ou déjà réclamé a un secret MORT : la
        // vérification échoue même quand le code est juste. Répondre « code
        // incorrect » enverrait un marchand qui tient l'objet regratter un
        // panneau déjà gratté, indéfiniment, pour un stand qu'aucun code
        // n'ouvrira plus.
        //
        // Et cela ne révèle rien : la page publique du stand distingue déjà
        // « activé » de « pas encore activé » à quiconque photographie le QR du
        // recto. On ne rend pas visible ici ce qui l'est déjà là-bas.
        if (! $stand->isClaimable()) {
            // On ne dit pas À QUI il appartient, en revanche : ce serait faire
            // de cet écran l'annuaire des commerces équipés.
            return $this->non(self::NOT_OPEN);
        }

        if (! $ok) {
            return $this->non(self::BAD_CODE);
        }

        return $this->rattacher($stand, $tenantId, $context);
    }

    /**
     * Le prochain libellé libre : « Table 1 », « Table 2 »…
     *
     * Taper quarante libellés est le second travail répétitif du parcours, après
     * les quarante codes. On numérote donc à la place du marchand, et il corrige
     * les rares exceptions — le comptoir, la terrasse.
     *
     * On prend le MAXIMUM, pas le compte : un stand retiré du service laisserait
     * un trou, et repartir du compte réattribuerait un numéro déjà porté par un
     * autre — deux « Table 12 » dans la même salle.
     */
    public function nextLabel(?string $tenantId, string $prefix = self::DEFAULT_PREFIX): string
    {
        $prefix = trim($prefix) !== '' ? trim($prefix) : self::DEFAULT_PREFIX;

        $max = 0;
        $motif = '/^'.preg_quote($prefix, '/').'\s*(\d+)$/iu';

        $labels = Stand::ofBusiness($tenantId)
            ->whereNotNull('location_label')
            ->pluck('location_label');

        foreach ($labels as $label) {
            if (preg_match($motif, trim((string) $label), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $prefix.' '.($max + 1);
    }

    /* ---------------- interne ---------------- */

    /**
     * Rattache le stand, sous verrou de ligne.
     *
     * Le re-contrôle DANS la transaction n'est pas du zèle : entre la
     * vérification plus haut et l'écriture, un autre commerce peut avoir réclamé
     * le même stand. Sans le verrou, les deux lisent « libre » puis écrivent
     * tour à tour, et le second efface le premier sans qu'aucune erreur ne
     * remonte.
     *
     * @return array{result:string, stand:?Stand, label:?string}
     */
    private function rattacher(Stand $stand, string $tenantId, array $context): array
    {
        $resultat = DB::transaction(function () use ($stand, $tenantId, $context) {
            $verrouille = Stand::with('batch')->whereKey($stand->id)->lockForUpdate()->first();

            if (! $verrouille) {
                return $this->non(self::NOT_FOUND);
            }

            if ($verrouille->tenant_id === $tenantId
                && StandState::resolvesToBusiness($verrouille->digital_state)) {
                return [
                    'result' => self::ALREADY_MINE,
                    'stand'  => $verrouille,
                    'label'  => $verrouille->location_label,
                ];
            }

            if (! $verrouille->isClaimable()) {
                return $this->non(self::NOT_OPEN);
            }

            // Le libellé se calcule ICI, une fois le verrou tenu : calculé
            // avant, deux activations simultanées porteraient le même numéro.
            $label = $context['location_label'] ?? null;
            if ($label === null || trim($label) === '') {
                $label = $this->nextLabel($tenantId, $context['label_prefix'] ?? self::DEFAULT_PREFIX);
            }

            $avant = $verrouille->digital_state;

            $verrouille->forceFill([
                'tenant_id'      => $tenantId,
                'digital_state'  => StandState::ACTIVE,
                'physical_state' => StandState::SOLD,
                'holder_type'    => 'business',
                'holder_id'      => null,
                'target_module'  => $context['target_module'] ?? 'menu',
                'location_label' => mb_substr(trim((string) $label), 0, 60),
                'claimed_at'     => now(),
                // Une réservation en cours est consommée par l'activation :
                // laisser le jeton vivre permettrait de rejouer la réclamation.
                'claim_reserved_until' => null,
                'claim_reserved_token' => null,
            ])->save();

            StandEvent::create([
                'stand_id'   => $verrouille->id,
                'event'      => StandEvent::CLAIMED,
                'from_state' => $avant,
                'to_state'   => StandState::ACTIVE,
                'actor_type' => 'business',
                'actor_name' => $context['actor_name'] ?? null,
                'tenant_id'  => $tenantId,
                'ip'         => $context['ip'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                // Par quel parcours — utile en litige pour distinguer une
                // réclamation publique d'une activation en salle.
                'meta'       => ['via' => 'scan'],
            ]);

            return [
                'result' => self::OK,
                'stand'  => $verrouille,
                'label'  => $verrouille->location_label,
            ];
        });

        if (in_array($resultat['result'], [self::OK, self::ALREADY_MINE], true)) {
            // Sans cet oubli-là, l'aiguillage continuerait de servir l'ancienne
            // destination une heure durant — c'est-à-dire une page « non activé »
            // à un client attablé devant un stand qui vient de l'être.
            $this->resolver->forget($stand->public_id);
        }

        return $resultat;
    }

    /** @return array{result:string, stand:null, label:null} */
    private function non(string $result): array
    {
        return ['result' => $result, 'stand' => null, 'label' => null];
    }
}
