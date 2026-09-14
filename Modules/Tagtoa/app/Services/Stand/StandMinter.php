<?php

namespace Modules\Tagtoa\App\Services\Stand;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandBatch;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Support\Stand\StandId;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA — fabriquer un lot de Smart Stands.
 *
 * Une commande, dix mille objets. Rien n'est saisi à la main : la saisie
 * manuelle est la seule source réaliste de doublons, et un doublon signifie
 * deux commerces qui se disputent le même identifiant.
 *
 * LA RÈGLE QUI GOUVERNE TOUT CE FICHIER : le secret en clair ne touche JAMAIS
 * la base. Il naît ici, part vers le fichier d'impression, et disparaît de la
 * mémoire. Seul son hachage est conservé — même règle que le PIN des employés
 * et l'UID des cartes NFC. Une fuite de la base ne donne alors aucun stand à
 * personne.
 *
 * Le fichier d'impression est le maillon faible de toute l'architecture : il
 * compromet un lot entier — dix mille commerces — en un seul fichier. Il est
 * écrit avec des permissions restreintes et doit être détruit après le tirage.
 */
class StandMinter
{
    /**
     * Hachage bcrypt d'une chaîne qu'aucun générateur ne produit.
     *
     * Sert à dépenser le même temps qu'une vérification réelle quand le stand
     * n'existe pas, n'est plus réclamable, ou que le code est mal formé. Sans
     * cela, un code faux répondrait en 5 ms et un code juste en 80 ms : le
     * chronomètre remplacerait la connaissance du code.
     */
    private const LEURRE = '$2y$10$uvHNVlOmKpxyS7BF5aIGd.onpUoMc/VWDsIexHzKRNbrbWYE52TC.';

    /**
     * Fabrique un lot.
     *
     * @return array{batch:StandBatch, secrets:array<string,string>}
     *         `secrets` est en clair et destiné UNIQUEMENT au fichier
     *         d'impression. L'appelant doit l'écrire puis l'oublier.
     */
    public function mint(
        string $code,
        int $quantity,
        string $prefix = StandId::PREFIX,
        int $start = 1,
        array $meta = []
    ): array {
        $quantity = max(1, $quantity);
        $start = max(1, $start);
        $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $prefix) ?: StandId::PREFIX);

        $secrets = [];

        $batch = DB::transaction(function () use ($code, $quantity, $prefix, $start, $meta, &$secrets) {
            $batch = StandBatch::create([
                'code'         => $code,
                'quantity'     => $quantity,
                'id_prefix'    => $prefix,
                'range_start'  => $start,
                'range_end'    => $start + $quantity - 1,
                'manufacturer' => $meta['manufacturer'] ?? null,
                // Ce qui a réellement été posé dans l'objet : un lot fabriqué
                // autrement se diagnostique autrement, des années plus tard.
                'hardware'     => $meta['hardware'] ?? 'NTAG213 · 144o · verrouillé',
                'produced_at'  => now(),
            ]);

            // Par paquets : dix mille INSERT un par un, c'est dix mille
            // allers-retours et plusieurs minutes.
            foreach (array_chunk(range($start, $start + $quantity - 1), 500) as $tranche) {
                $lignes = [];

                foreach ($tranche as $serial) {
                    $publicId = StandId::format($serial, $prefix);
                    $secret   = StandId::makeSecret();

                    // Le clair ne sort d'ici que vers le fichier d'impression.
                    $secrets[$publicId] = $secret;

                    $lignes[] = [
                        'batch_id'       => $batch->id,
                        'public_id'      => $publicId,
                        'serial'         => $serial,
                        'secret_hash'    => Hash::make($secret),
                        'secret_version' => 1,
                        'physical_state' => StandState::MANUFACTURED,
                        'digital_state'  => StandState::UNCLAIMED,
                        'holder_type'    => 'platform',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }

                Stand::insert($lignes);
            }

            // UN événement pour le lot, pas dix mille : le journal sert à
            // retracer ce qui arrive à un objet, et « il a été fabriqué » est
            // déjà écrit dans son appartenance au lot.
            StandEvent::create([
                'stand_id'   => Stand::where('batch_id', $batch->id)->orderBy('serial')->value('id'),
                'event'      => StandEvent::MINTED,
                'to_state'   => StandState::MANUFACTURED,
                'actor_type' => 'system',
                'meta'       => ['batch' => $code, 'quantity' => $quantity, 'prefix' => $prefix],
            ]);

            return $batch;
        });

        return ['batch' => $batch, 'secrets' => $secrets];
    }

    /**
     * Vérifie un secret contre un stand.
     *
     * Comparaison en TEMPS CONSTANT, et surtout : on hache même quand le stand
     * n'existe pas ou n'est plus réclamable. Sans cela, un code faux répondrait
     * en 5 ms et un code juste en 80 ms, et le chronomètre remplacerait la
     * connaissance du code.
     */
    public function verify(?Stand $stand, ?string $secret): bool
    {
        $secret = StandId::normalizeSecret($secret);

        // Hachage LEURRE — un vrai bcrypt, d'un mot que rien ne produit.
        //
        // Il doit être valide : Laravel refuse de vérifier contre autre chose,
        // et un hachage bidon ferait remonter une exception à l'endroit même
        // où l'on cherchait à ne rien révéler. Son rôle est de dépenser le même
        // temps processeur qu'une vérification réelle.
        $leurre = self::LEURRE;

        if (! $stand || ! $stand->isClaimable() || $stand->secret_hash === null) {
            Hash::check($secret === '' ? 'x' : $secret, $leurre);

            return false;
        }

        if (! StandId::isValidSecret($secret)) {
            Hash::check('x', $leurre);

            return false;
        }

        return Hash::check($secret, $stand->secret_hash);
    }

    /**
     * Réémet le secret d'un stand.
     *
     * Panneau gratté en transit, perte déclarée, litige : l'ancien code cesse
     * de valoir. `secret_version` avance pour que l'historique montre qu'il y a
     * eu réémission — un stand dont le secret change deux fois en un mois
     * mérite qu'on regarde pourquoi.
     */
    public function reissue(Stand $stand, ?string $reason = null): string
    {
        $secret = StandId::makeSecret();

        $stand->forceFill([
            'secret_hash'    => Hash::make($secret),
            'secret_version' => (int) $stand->secret_version + 1,
        ])->save();

        StandEvent::create([
            'stand_id'   => $stand->id,
            'event'      => StandEvent::REPLACED,
            'actor_type' => 'admin',
            'meta'       => ['reason' => $reason, 'version' => $stand->secret_version],
        ]);

        return $secret;
    }
}
