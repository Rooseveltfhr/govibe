<?php

namespace App\Services;

use App\Models\CommandeAbonnement;
use App\Models\ComptePortail;
use App\Models\DemandeAgentIa;
use App\Models\InscriptionSession;
use Illuminate\Support\Facades\DB;

class RattachementServicesClient
{
    /**
     * Rattache au compte client ce qu'il a déjà commandé avant d'avoir un
     * compte : sinon le portail s'ouvre sur une page vide alors que la
     * personne travaille avec GOVIBE depuis des mois.
     *
     * Appelé uniquement après vérification de l'adresse. Avant, rien ne prouve
     * que la personne possède l'email qu'elle a saisi — et le rattachement
     * donnerait accès aux commandes d'un tiers.
     *
     * @return int Nombre d'éléments rattachés
     */
    public function rattacher(ComptePortail $compte): int
    {
        if (! $compte->estVerifie()) {
            return 0;
        }

        $email = mb_strtolower($compte->email);
        $telephone = $this->chiffres($compte->telephone);

        return DB::transaction(function () use ($compte, $email, $telephone) {
            $total = 0;

            $total += DemandeAgentIa::whereNull('client_id')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->update(['client_id' => $compte->client_id]);

            $total += CommandeAbonnement::whereNull('client_id')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->update(['client_id' => $compte->client_id]);

            // Les inscriptions aux formations ne portent qu'un numéro WhatsApp.
            // On ne rapproche que sur un numéro identique chiffre pour chiffre :
            // un rapprochement approximatif exposerait l'inscription d'un autre.
            if ($telephone !== '') {
                $total += InscriptionSession::whereNull('client_id')
                    ->get()
                    ->filter(fn ($i) => $this->chiffres($i->whatsapp) === $telephone)
                    ->each(fn ($i) => $i->update(['client_id' => $compte->client_id]))
                    ->count();
            }

            return $total;
        });
    }

    /** Réduit un numéro à ses chiffres : « +509 3712-4455 » et « 50937124455 » sont le même. */
    private function chiffres(?string $numero): string
    {
        return preg_replace('/\D+/', '', (string) $numero) ?? '';
    }
}
