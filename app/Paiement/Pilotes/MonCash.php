<?php

namespace App\Paiement\Pilotes;

use App\Models\Paiement;
use App\Models\PasserellePaiement;
use App\Paiement\ResultatInitiation;
use App\Paiement\ResultatVerification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MonCash (Digicel Haïti).
 *
 * Déroulé : jeton OAuth2, création du paiement, redirection du client vers
 * MonCash, puis — au retour — interrogation de MonCash sur notre propre
 * numéro de commande.
 *
 * Le point qui compte : MonCash ne rappelle pas le serveur. C'est le
 * navigateur du client qui revient, et une URL de retour se falsifie. Le
 * verdict est donc toujours demandé à MonCash, jamais lu dans le retour.
 *
 * Les adresses et les noms de champs suivent la documentation MonCash
 * Business ; elles restent configurables pour absorber une évolution de leur
 * côté sans redéployer du code.
 */
class MonCash implements PiloteApi
{
    public static function cle(): string
    {
        return 'moncash';
    }

    public function devises(): array
    {
        return ['HTG'];
    }

    public function champsRequis(): array
    {
        return [
            'client_id' => 'Identifiant client MonCash Business',
            'client_secret' => 'Clé secrète MonCash Business',
        ];
    }

    public function initier(Paiement $paiement, PasserellePaiement $passerelle): ResultatInitiation
    {
        if ($paiement->devise !== 'HTG') {
            return ResultatInitiation::echec('MonCash n\'encaisse que des gourdes.');
        }

        $jeton = $this->jetonAcces($passerelle);

        if (! $jeton) {
            return ResultatInitiation::echec("Impossible d'obtenir un jeton MonCash. Vérifiez les clés dans l'ERP.");
        }

        try {
            $reponse = Http::withToken($jeton)
                ->acceptJson()
                ->timeout(20)
                ->post($this->base($passerelle).'/Api/v1/CreatePayment', [
                    // MonCash n'accepte pas de décimales.
                    'amount' => (int) round((float) $paiement->montant),
                    'orderId' => $paiement->reference,
                ]);
        } catch (\Throwable $e) {
            Log::warning('MonCash CreatePayment injoignable', ['paiement' => $paiement->reference]);

            return ResultatInitiation::echec('MonCash est injoignable. Réessayez dans un moment.');
        }

        $corps = $reponse->json() ?? [];
        $token = data_get($corps, 'payment_token.token');

        if (! $reponse->successful() || ! $token) {
            return ResultatInitiation::echec(
                'MonCash a refusé la création du paiement.',
                $this->nettoyer($corps)
            );
        }

        return ResultatInitiation::redirection(
            $this->base($passerelle).'/Moncash-middleware/Payment/Redirect?token='.urlencode($token),
            $token,
            $this->nettoyer($corps)
        );
    }

    public function verifier(Paiement $paiement, PasserellePaiement $passerelle): ResultatVerification
    {
        $jeton = $this->jetonAcces($passerelle);

        if (! $jeton) {
            return ResultatVerification::enAttente(['erreur' => 'jeton indisponible']);
        }

        try {
            $reponse = Http::withToken($jeton)
                ->acceptJson()
                ->timeout(20)
                ->post($this->base($passerelle).'/Api/v1/RetrieveOrderPayment', [
                    'orderId' => $paiement->reference,
                ]);
        } catch (\Throwable $e) {
            // Injoignable n'est pas « échoué » : le client a peut-être payé.
            // Le dossier reste en attente et sera revérifié.
            return ResultatVerification::enAttente(['erreur' => 'MonCash injoignable']);
        }

        $corps = $reponse->json() ?? [];
        $transaction = data_get($corps, 'payment.transaction_id')
            ?? data_get($corps, 'payment.reference');
        $message = strtolower((string) data_get($corps, 'payment.message', ''));
        $montant = data_get($corps, 'payment.cost');

        if (! $reponse->successful()) {
            return ResultatVerification::enAttente($this->nettoyer($corps));
        }

        if ($message === 'successful' && $transaction) {
            return ResultatVerification::reussi(
                (string) $transaction,
                $montant !== null ? (float) $montant : null,
                'HTG',
                $this->nettoyer($corps)
            );
        }

        // Une commande inconnue de MonCash reste en attente : le client n'a
        // peut-être pas encore terminé. Ce n'est pas un échec définitif.
        return ResultatVerification::enAttente($this->nettoyer($corps));
    }

    // ── Interne ──────────────────────────────────────────

    private function base(PasserellePaiement $passerelle): string
    {
        return rtrim(config(
            $passerelle->environnement === 'production'
                ? 'paiement.moncash.base_production'
                : 'paiement.moncash.base_test'
        ), '/');
    }

    private function jetonAcces(PasserellePaiement $passerelle): ?string
    {
        $config = $passerelle->identifiants ?? [];
        $id = $config['client_id'] ?? null;
        $secret = $config['client_secret'] ?? null;

        if (! $id || ! $secret) {
            return null;
        }

        try {
            $reponse = Http::asForm()
                ->withBasicAuth($id, $secret)
                ->acceptJson()
                ->timeout(20)
                ->post($this->base($passerelle).'/Api/oauth/token', [
                    'scope' => 'read,write',
                    'grant_type' => 'client_credentials',
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        return $reponse->successful() ? data_get($reponse->json(), 'access_token') : null;
    }

    /**
     * Retire ce qui ne doit jamais atterrir en base ni dans un journal.
     * Une charge utile est conservée pour la réconciliation, pas pour y
     * stocker des jetons réutilisables.
     */
    private function nettoyer(array $corps): array
    {
        foreach (['access_token', 'refresh_token', 'client_secret', 'authorization'] as $cle) {
            unset($corps[$cle]);
        }

        if (isset($corps['payment_token']['token'])) {
            $corps['payment_token']['token'] = '***';
        }

        return $corps;
    }
}
