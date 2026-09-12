<?php

namespace App\Models;

use App\Paiement\PaiementConstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Une commande passée depuis le site : ce que le visiteur a demandé, au tarif
 * qui lui a été montré, et par quel moyen il règle.
 *
 * Distincte de l'abonnement : tant que le paiement n'est pas constaté, rien ne
 * doit être facturé ni approvisionné. L'abonnement naît de l'approbation.
 */
class CommandeAbonnement extends Model implements PaiementConstate
{
    use HasUuids;

    protected $table = 'commandes_abonnement';

    protected $fillable = [
        'uuid', 'reference',
        'plan_id', 'service', 'plan_nom', 'cycle',
        'prix_unitaire', 'tca_taux', 'montant_ttc', 'devise',
        'devise_paiement', 'taux_change', 'montant_a_payer',
        'client_id', 'nom_complet', 'entreprise', 'email', 'whatsapp',
        'domaine', 'domaine_origine', 'duree_annees', 'besoins', 'details',
        'passerelle_id', 'passerelle_nom', 'mode_paiement',
        'paiement_id', 'preuve_paiement_id',
        'statut', 'abonnement_id', 'notes_internes', 'traitee_par', 'traitee_le', 'ip',
    ];

    protected $casts = [
        'prix_unitaire' => 'decimal:2',
        'tca_taux' => 'decimal:2',
        'montant_ttc' => 'decimal:2',
        'taux_change' => 'decimal:6',
        'montant_a_payer' => 'decimal:2',
        'duree_annees' => 'integer',
        'details' => 'array',
        'traitee_le' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ── Relations ────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function passerelle(): BelongsTo
    {
        return $this->belongsTo(PasserellePaiement::class, 'passerelle_id');
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class, 'paiement_id');
    }

    public function preuve(): BelongsTo
    {
        return $this->belongsTo(PreuvePaiement::class, 'preuve_paiement_id');
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traitee_par');
    }

    public static function genererReference(): string
    {
        do {
            $reference = 'CM-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    // ── Listes de référence ──────────────────────────────

    /**
     * Le parcours d'une commande.
     *
     * « livree » est l'état final : le service tourne et l'abonnement facture.
     * Tant que la mise en service n'est pas automatisée, c'est un agent qui
     * fait passer de « en_traitement » à « livree ».
     */
    public static function statuts(): array
    {
        return [
            'nouvelle' => 'Nouvelle',
            'paiement_attente' => 'Paiement en attente',
            'paiement_recu' => 'Paiement reçu',
            'en_traitement' => 'En cours de mise en service',
            'livree' => 'Livrée',
            'annulee' => 'Annulée',
        ];
    }

    /** D'où vient le nom de domaine que le client veut utiliser. */
    public static function originesDomaine(): array
    {
        return [
            'a_enregistrer' => 'À enregistrer par GOVIBE',
            'deja_detenu' => 'Je le possède déjà',
            'a_transferer' => 'À transférer vers GOVIBE',
            'aucun' => 'Pas encore de nom de domaine',
        ];
    }

    // ── Libellés ─────────────────────────────────────────

    public function getStatutLibelleAttribute(): string
    {
        return self::statuts()[$this->statut] ?? $this->statut;
    }

    public function getServiceLibelleAttribute(): string
    {
        return Plan::services()[$this->service] ?? $this->service;
    }

    public function getCycleLibelleAttribute(): string
    {
        return Plan::cycles()[$this->cycle] ?? $this->cycle;
    }

    public function getOrigineDomaineLibelleAttribute(): ?string
    {
        return $this->domaine_origine
            ? (self::originesDomaine()[$this->domaine_origine] ?? $this->domaine_origine)
            : null;
    }

    /** Le total tel qu'il a été annoncé au client, dans la devise qu'il règle. */
    public function getMontantAfficheAttribute(): string
    {
        return $this->formater((float) $this->montant_a_payer).' '.$this->devise_paiement;
    }

    /**
     * Le montant d'origine, affiché à côté du converti. Rien n'est montré
     * quand aucune conversion n'a eu lieu : répéter deux fois la même somme
     * laisse croire à un double paiement.
     */
    public function getMontantOrigineAfficheAttribute(): ?string
    {
        if ($this->devise_paiement === $this->devise) {
            return null;
        }

        return $this->formater((float) $this->montant_ttc).' '.$this->devise;
    }

    private function formater(float $v): string
    {
        return floor($v) == $v
            ? number_format($v, 0, ',', ' ')
            : number_format($v, 2, ',', ' ');
    }

    // ── États ────────────────────────────────────────────

    /** Une commande réglée, qu'il reste à mettre en service. */
    public function estPayee(): bool
    {
        return in_array($this->statut, ['paiement_recu', 'en_traitement', 'livree'], true);
    }

    /** Un abonnement a déjà été créé : le recréer facturerait deux contrats. */
    public function estActivee(): bool
    {
        return $this->abonnement_id !== null;
    }

    public function scopeAtraiter(Builder $q): Builder
    {
        return $q->whereIn('statut', ['nouvelle', 'paiement_attente', 'paiement_recu']);
    }

    public function scopePourService(Builder $q, string $service): Builder
    {
        return $q->where('service', $service);
    }

    // ── Verdict du paiement ──────────────────────────────

    /**
     * Le PaiementService appelle ceci quel que soit le chemin suivi : retour du
     * navigateur, notification de la passerelle, approbation d'un agent.
     *
     * Rien n'est mis en service ici. Encaisser et livrer sont deux décisions :
     * un paiement reçu sur un nom de domaine déjà pris ne doit pas déclencher
     * un enregistrement qui échouera.
     */
    public function paiementReussi(Paiement $paiement): void
    {
        // Une commande déjà livrée ne revient pas en arrière : un retour de
        // passerelle rejoué ne doit pas effacer le travail fait depuis.
        if (in_array($this->statut, ['en_traitement', 'livree', 'annulee'], true)) {
            return;
        }

        $this->forceFill(['statut' => 'paiement_recu'])->save();
    }

    /**
     * Paiement refusé : la commande reste ouverte, en attente de règlement.
     * L'annuler priverait l'équipe d'un client qui va simplement repayer.
     */
    public function paiementRejete(Paiement $paiement): void
    {
        if (in_array($this->statut, ['paiement_recu', 'en_traitement', 'livree', 'annulee'], true)) {
            return;
        }

        $this->forceFill(['statut' => 'paiement_attente'])->save();
    }

    // ── Notification WhatsApp ────────────────────────────

    /**
     * Le message que le client envoie à l'équipe après sa commande.
     *
     * La commande est déjà enregistrée : le message prévient, il ne transporte
     * pas la donnée. wa.me ne peut pas joindre de fichier, donc la preuve de
     * paiement arrive par le formulaire, jamais par ce lien.
     */
    public function getMessageWhatsappAttribute(): string
    {
        $lignes = [
            'Bonjour GOVIBE, je viens de passer une commande sur govibeht.com.',
            '',
            'Référence : '.$this->reference,
            'Service : '.$this->service_libelle,
            'Offre : '.$this->plan_nom.' ('.$this->cycle_libelle.')',
            'Nom : '.$this->nom_complet,
        ];

        if ($this->entreprise) {
            $lignes[] = 'Entreprise : '.$this->entreprise;
        }

        if ($this->domaine) {
            $lignes[] = 'Domaine : '.$this->domaine
                .($this->origine_domaine_libelle ? ' — '.$this->origine_domaine_libelle : '');
        }

        if ($this->duree_annees) {
            $lignes[] = 'Durée : '.$this->duree_annees.' an'.($this->duree_annees > 1 ? 's' : '');
        }

        $lignes[] = 'Montant : '.$this->montant_affiche
            .($this->montant_origine_affiche ? ' (soit '.$this->montant_origine_affiche.')' : '');
        $lignes[] = 'Paiement : '.($this->passerelle_nom ?: 'à convenir');

        if ($this->preuve_paiement_id) {
            $lignes[] = 'Preuve envoyée sur le site : '.$this->preuve?->reference;
        }

        return implode("\n", $lignes);
    }

    public function getLienWhatsappAttribute(): string
    {
        $numero = preg_replace('/\D+/', '', (string) config('govibe.whatsapp_verification'));

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($this->message_whatsapp);
    }
}
