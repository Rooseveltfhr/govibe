<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DemandeAgentIa extends Model
{
    protected $table = 'demandes_agent_ia';

    protected $fillable = [
        'reference', 'agent_ia_id', 'agent_nom',
        'prix_installation', 'prix_mensuel', 'devise', 'sur_devis',
        'entreprise', 'responsable', 'email', 'telephone', 'secteur',
        'pays', 'ville', 'site_web',
        'objectifs', 'a_automatiser', 'volume_conversations', 'langues',
        'canal', 'integrations', 'message',
        'moyen_paiement', 'moyen_paiement_nom', 'statut_paiement', 'statut',
        'fournisseur', 'numero_whatsapp', 'url_agent', 'deploye_le',
        'notes_internes', 'ip',
    ];

    protected $casts = [
        'integrations' => 'array',
        'prix_installation' => 'decimal:2',
        'prix_mensuel' => 'decimal:2',
        'sur_devis' => 'boolean',
        'deploye_le' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AgentIa::class, 'agent_ia_id');
    }

    public static function genererReference(): string
    {
        do {
            $reference = 'AI-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    /** Le parcours d'un dossier, de la demande à l'agent en service. */
    public static function statuts(): array
    {
        return [
            'nouvelle' => 'Nouvelle demande',
            'analyse' => 'En analyse',
            'paiement_attente' => 'Paiement en attente',
            'paiement_recu' => 'Paiement reçu',
            'configuration' => 'En configuration',
            'test' => 'Agent en test',
            'actif' => 'Agent actif',
            'termine' => 'Terminé',
        ];
    }

    public static function statutsPaiement(): array
    {
        return [
            'en_attente' => 'En attente',
            'preuve_envoyee' => 'Preuve envoyée',
            'recu' => 'Reçu',
            'sur_devis' => 'Sur devis',
        ];
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::statuts()[$this->statut] ?? $this->statut;
    }

    public function getStatutPaiementLibelleAttribute(): string
    {
        return self::statutsPaiement()[$this->statut_paiement] ?? $this->statut_paiement;
    }

    /**
     * Ce que le client règle à la commande : l'installation seule. Le service
     * mensuel démarre une fois l'agent en ligne — le faire payer d'avance
     * reviendrait à facturer un service pas encore rendu.
     */
    public function getTotalMaintenantAttribute(): ?float
    {
        return $this->sur_devis ? null : (float) $this->prix_installation;
    }

    public function getIntegrationsLisiblesAttribute(): array
    {
        $liste = AgentIa::integrationsDisponibles();

        return array_values(array_map(
            fn ($cle) => $liste[$cle] ?? $cle,
            $this->integrations ?? []
        ));
    }

    public function getCanalLisibleAttribute(): ?string
    {
        return AgentIa::canauxDisponibles()[$this->canal] ?? $this->canal;
    }

    public function getVolumeLisibleAttribute(): ?string
    {
        return AgentIa::volumesConversations()[$this->volume_conversations] ?? $this->volume_conversations;
    }

    /** Numéro WhatsApp de l'équipe GOVIBE, au format attendu par wa.me. */
    public const WHATSAPP = '50933988754';

    /**
     * Le message que le client envoie à GOVIBE depuis la confirmation.
     *
     * Aucun serveur ne peut écrire dans WhatsApp sans un numéro Cloud API :
     * c'est donc le client qui pousse le message, en un geste, depuis un lien
     * pré-rempli. La demande est déjà enregistrée à ce moment — le message
     * prévient l'équipe, il ne porte pas la donnée.
     */
    public function getMessageWhatsappAttribute(): string
    {
        $lignes = [
            'Bonjour, je viens de demander un Agent IA sur govibeht.com.',
            '',
            "Référence : {$this->reference}",
            "Agent : {$this->agent_nom}",
            "Entreprise : {$this->entreprise}",
            "Responsable : {$this->responsable}",
        ];

        if ($this->telephone) {
            $lignes[] = "Téléphone : {$this->telephone}";
        }
        if ($this->canal_lisible) {
            $lignes[] = "Canal : {$this->canal_lisible}";
        }
        if ($this->sur_devis) {
            $lignes[] = 'Tarification : sur devis';
        } elseif ($this->prix_installation !== null) {
            $lignes[] = 'Installation : '.$this->montantAffiche((float) $this->prix_installation);
        }

        // Le texte libre est tronqué : WhatsApp coupe les liens trop longs,
        // et le détail complet attend déjà dans l'ERP.
        if ($this->objectifs) {
            $lignes[] = 'Objectif : '.Str::limit($this->objectifs, 180);
        }

        return implode("\n", $lignes);
    }

    public function getLienWhatsappAttribute(): string
    {
        return 'https://wa.me/'.self::WHATSAPP.'?text='.rawurlencode($this->message_whatsapp);
    }

    public function scopeEnCours(Builder $query): Builder
    {
        return $query->whereNotIn('statut', ['termine']);
    }

    public function scopeAtraiter(Builder $query): Builder
    {
        return $query->whereIn('statut', ['nouvelle', 'analyse']);
    }

    public function montantAffiche(?float $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        return (floor($valeur) == $valeur
            ? number_format($valeur, 0, ',', ' ')
            : number_format($valeur, 2, ',', ' ')).' '.$this->devise;
    }
}
