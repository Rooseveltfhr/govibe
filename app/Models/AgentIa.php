<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentIa extends Model
{
    protected $table = 'agents_ia';

    protected $fillable = [
        'slug', 'nom', 'categorie', 'icone', 'description_courte',
        'capacites', 'canaux', 'prix_installation', 'prix_mensuel',
        'devise', 'sur_devis', 'avertissement', 'actif', 'ordre',
    ];

    protected $casts = [
        'capacites' => 'array',
        'canaux' => 'array',
        'prix_installation' => 'decimal:2',
        'prix_mensuel' => 'decimal:2',
        'sur_devis' => 'boolean',
        'actif' => 'boolean',
        'ordre' => 'integer',
    ];

    public function demandes(): HasMany
    {
        return $this->hasMany(DemandeAgentIa::class, 'agent_ia_id');
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true)->orderBy('ordre')->orderBy('nom');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** « À partir de 200 USD », ou « Sur devis ». */
    public function getPrixAfficheAttribute(): string
    {
        if ($this->sur_devis || $this->prix_installation === null) {
            return 'Sur devis';
        }

        return 'À partir de '.$this->montant($this->prix_installation).' '.$this->devise;
    }

    public function getPrixMensuelAfficheAttribute(): ?string
    {
        if ($this->sur_devis || $this->prix_mensuel === null) {
            return null;
        }

        return $this->montant($this->prix_mensuel).' '.$this->devise.' / mois';
    }

    private function montant(float|string $valeur): string
    {
        $v = (float) $valeur;

        // Un prix rond s'affiche sans décimales : « 200 USD », pas « 200,00 ».
        return floor($v) == $v
            ? number_format($v, 0, ',', ' ')
            : number_format($v, 2, ',', ' ');
    }

    /**
     * Les canaux proposés au client dans le formulaire. Un agent qui n'a pas
     * la voix ne doit pas la faire choisir.
     */
    public static function canauxDisponibles(): array
    {
        return [
            'whatsapp' => 'WhatsApp',
            'site' => 'Site web',
            'voix' => 'Voix',
            'whatsapp_site' => 'WhatsApp + Site web',
            'whatsapp_voix' => 'WhatsApp + Voix',
            'omnicanal' => 'Omnicanal',
        ];
    }

    public static function integrationsDisponibles(): array
    {
        return [
            'booking' => 'Réservations',
            'crm' => 'CRM',
            'erp' => 'ERP',
            'paiement' => 'Paiement',
            'agenda' => 'Agenda',
            'ecommerce' => 'E-commerce',
            'api' => 'Intégration API',
            'humain' => 'Transfert vers un humain',
        ];
    }

    public static function volumesConversations(): array
    {
        return [
            'moins_500' => 'Moins de 500 / mois',
            '500_2000' => '500 à 2 000 / mois',
            '2000_10000' => '2 000 à 10 000 / mois',
            'plus_10000' => 'Plus de 10 000 / mois',
            'inconnu' => 'Je ne sais pas encore',
        ];
    }
}
