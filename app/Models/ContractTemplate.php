<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractTemplate extends Model
{
    protected $fillable = [
        'title', 'category', 'description', 'body', 'variables', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'template_id');
    }

    public static function categoryLabel(string $cat): string
    {
        return match ($cat) {
            'coworking'     => 'Coworking',
            'erp'           => 'ERP / SaaS',
            'formation'     => 'Formation',
            'developpement' => 'Développement',
            'nda'           => 'Confidentialité (NDA)',
            default         => 'Autre',
        };
    }

    /** Remplace les {{variables}} par les valeurs fournies. */
    public function renderWith(array $values): string
    {
        $body = $this->body;
        foreach ($values as $key => $val) {
            $body = str_replace('{{'.$key.'}}', e($val), $body);
            $body = str_replace('{{ '.$key.' }}', e($val), $body);
        }
        return $body;
    }

    /** Retourne les noms de toutes les {{variables}} présentes dans le body. */
    public function extractVariables(): array
    {
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $this->body, $m);
        return array_unique($m[1] ?? []);
    }

    /** Plans par défaut — 5 templates GOVIBE prêts à l'emploi. */
    public static function defaults(): array
    {
        return [
            [
                'title'     => 'Contrat de location Coworking',
                'category'  => 'coworking',
                'description' => 'Location d\'espace de coworking (hot desk, bureau dédié ou privé).',
                'variables' => ['client_name','client_address','client_phone','plan_type','price','currency','start_date','end_date','payment_terms'],
                'body'      => self::tplCoworking(),
            ],
            [
                'title'     => 'Contrat de services ERP / SaaS',
                'category'  => 'erp',
                'description' => 'Abonnement au système ERP GOVIBE avec maintenance et support.',
                'variables' => ['client_name','client_address','plan_name','price','currency','billing_cycle','start_date','support_level'],
                'body'      => self::tplERP(),
            ],
            [
                'title'     => 'Contrat de prestation de formation',
                'category'  => 'formation',
                'description' => 'Prestation de formation professionnelle (formation entreprise ou individuelle).',
                'variables' => ['client_name','formation_title','nb_hours','price','currency','start_date','end_date','lieu'],
                'body'      => self::tplFormation(),
            ],
            [
                'title'     => 'Contrat de développement logiciel',
                'category'  => 'developpement',
                'description' => 'Développement d\'application web, mobile ou système sur mesure.',
                'variables' => ['client_name','project_title','scope','price','currency','payment_schedule','delivery_date'],
                'body'      => self::tplDev(),
            ],
            [
                'title'     => 'Accord de confidentialité (NDA)',
                'category'  => 'nda',
                'description' => 'Non-Disclosure Agreement entre GOVIBE et un partenaire / client.',
                'variables' => ['partner_name','partner_type','duration_years','signing_date'],
                'body'      => self::tplNDA(),
            ],
        ];
    }

    /* ── Templates HTML ─────────────────────────────────────────────────── */

    private static function header(string $title): string
    {
        return "<div style='text-align:center;margin-bottom:2rem;'>
<h2 style='font-size:1.5rem;font-weight:900;color:#0a0a0a;letter-spacing:.04em;margin:0;'>{$title}</h2>
<p style='color:#DC2626;font-weight:700;font-size:.9rem;margin:.3rem 0 0;'>GOVIBE STARTUP LLC — Port-au-Prince, Haïti</p>
</div>
<hr style='border:2px solid #DC2626;margin-bottom:2rem;'>
";
    }

    private static function footer(): string
    {
        return "
<div style='margin-top:3rem;'>
<hr style='border:1px solid #e5e7eb;margin-bottom:2rem;'>
<div style='display:flex;justify-content:space-between;gap:2rem;flex-wrap:wrap;'>
<div style='flex:1;min-width:180px;'>
  <p style='font-weight:700;margin-bottom:.5rem;'>Pour GOVIBE STARTUP LLC</p>
  <div style='border-top:2px solid #0a0a0a;margin-top:3rem;padding-top:.5rem;font-size:.85rem;color:#6b7280;'>
    Signature &amp; Cachet
  </div>
  <p style='font-size:.8rem;color:#6b7280;margin-top:.3rem;'>Roosevelt Forestal — CEO</p>
</div>
<div style='flex:1;min-width:180px;'>
  <p style='font-weight:700;margin-bottom:.5rem;'>Le Client</p>
  <div style='border-top:2px solid #0a0a0a;margin-top:3rem;padding-top:.5rem;font-size:.85rem;color:#6b7280;'>
    Signature &amp; Cachet
  </div>
</div>
</div>
<p style='color:#9ca3af;font-size:.75rem;text-align:center;margin-top:2rem;'>
  GOVIBE STARTUP LLC · Port-au-Prince, Haïti · govibeht.com · contact@govibeht.com · +509 3398-8754
</p>
</div>";
    }

    private static function tplCoworking(): string
    {
        return self::header('CONTRAT DE LOCATION D\'ESPACE COWORKING')."
<p><strong>ENTRE LES SOUSSIGNÉS :</strong></p>
<p><strong>GOVIBE STARTUP LLC</strong>, société légalement enregistrée à Port-au-Prince, Haïti, représentée par Roosevelt Forestal, ci-après dénommée <em>\"GOVIBE\"</em> ;</p>
<p>ET</p>
<p><strong>{{client_name}}</strong>, domicilié(e) / établi(e) à {{client_address}}, téléphone : {{client_phone}}, ci-après dénommé(e) <em>\"le Locataire\"</em>.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 1 — OBJET</h3>
<p>GOVIBE met à disposition du Locataire un espace de type <strong>{{plan_type}}</strong> dans ses locaux Coworking Space, situés à Port-au-Prince, Haïti.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 2 — DURÉE</h3>
<p>Le présent contrat prend effet le <strong>{{start_date}}</strong> et se termine le <strong>{{end_date}}</strong>, sauf renouvellement tacite ou résiliation.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 3 — TARIF ET PAIEMENT</h3>
<p>Le tarif convenu est de <strong>{{price}} {{currency}}</strong> par mois.</p>
<p>Modalités de paiement : {{payment_terms}}.</p>
<p>Tout retard de paiement de plus de 7 jours entraîne une pénalité de 5% du montant dû.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 4 — SERVICES INCLUS</h3>
<ul>
<li>Accès à l'espace pendant les heures d'ouverture (8h – 20h, lun-sam)</li>
<li>Wi-Fi haut débit sécurisé</li>
<li>Électricité et climatisation</li>
<li>Accès aux espaces communs (salle de réunion sur réservation)</li>
<li>Adresse postale professionnelle</li>
</ul>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 5 — OBLIGATIONS DU LOCATAIRE</h3>
<p>Le Locataire s'engage à utiliser l'espace dans le respect des règles de vie commune, à ne pas perturber les autres occupants et à ne pas sous-louer l'espace sans accord préalable de GOVIBE.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 6 — RÉSILIATION</h3>
<p>Chaque partie peut résilier le présent contrat avec un préavis de 15 jours par écrit (email ou WhatsApp). GOVIBE se réserve le droit de résilier sans préavis en cas de non-paiement ou de manquement grave.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 7 — LOI APPLICABLE</h3>
<p>Le présent contrat est régi par la législation haïtienne. Tout litige sera soumis aux juridictions compétentes de Port-au-Prince.</p>
".self::footer();
    }

    private static function tplERP(): string
    {
        return self::header('CONTRAT DE SERVICES ERP / SAAS')."
<p><strong>ENTRE LES SOUSSIGNÉS :</strong></p>
<p><strong>GOVIBE STARTUP LLC</strong>, fournisseur de solutions numériques, représentée par Roosevelt Forestal, ci-après <em>\"GOVIBE\"</em> ;</p>
<p>ET <strong>{{client_name}}</strong>, {{client_address}}, ci-après <em>\"le Client\"</em>.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 1 — OBJET</h3>
<p>GOVIBE fournit au Client l'accès au système ERP GOVIBE, plan <strong>{{plan_name}}</strong>, comprenant les modules définis dans la fiche technique correspondante.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 2 — DURÉE ET ENTRÉE EN VIGUEUR</h3>
<p>Le contrat prend effet le <strong>{{start_date}}</strong> pour une durée de 12 mois renouvelable automatiquement.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 3 — TARIFICATION</h3>
<p>Abonnement : <strong>{{price}} {{currency}}</strong> / {{billing_cycle}}.</p>
<p>Les tarifs sont révisables annuellement avec préavis de 30 jours.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 4 — SERVICES</h3>
<ul>
<li>Accès hébergé au système ERP GOVIBE (multi-utilisateurs)</li>
<li>Mises à jour et maintenance incluses</li>
<li>Support technique : {{support_level}}</li>
<li>Formation initiale (2h) incluse dans la mise en place</li>
<li>Sauvegarde quotidienne des données</li>
</ul>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 5 — PROPRIÉTÉ DES DONNÉES</h3>
<p>Les données du Client restent sa propriété exclusive. GOVIBE s'engage à ne pas les divulguer à des tiers et à les restituer sur demande en cas de résiliation.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 6 — LIMITATION DE RESPONSABILITÉ</h3>
<p>GOVIBE garantit une disponibilité de 99% (hors maintenance planifiée). En cas d'interruption prolongée non planifiée, le Client bénéficie d'une compensation proratisée sur la prochaine facture.</p>
".self::footer();
    }

    private static function tplFormation(): string
    {
        return self::header('CONTRAT DE PRESTATION DE FORMATION')."
<p><strong>ENTRE LES SOUSSIGNÉS :</strong></p>
<p><strong>GOVIBE STARTUP LLC / GOVIBE Academy</strong>, organisme de formation, ci-après <em>\"le Prestataire\"</em> ;</p>
<p>ET <strong>{{client_name}}</strong>, ci-après <em>\"le Bénéficiaire\"</em>.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 1 — OBJET</h3>
<p>Le Prestataire s'engage à dispenser la formation intitulée : <strong>{{formation_title}}</strong>.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 2 — MODALITÉS</h3>
<ul>
<li>Durée : <strong>{{nb_hours}} heures</strong></li>
<li>Lieu : {{lieu}}</li>
<li>Période : du {{start_date}} au {{end_date}}</li>
</ul>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 3 — TARIF</h3>
<p>Le coût total de la formation est de <strong>{{price}} {{currency}}</strong>, payable selon les modalités convenues.</p>
<p>Un acompte de 50% est dû à la signature. Le solde est exigible avant le premier jour de formation.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 4 — MATÉRIEL PÉDAGOGIQUE</h3>
<p>Le Prestataire fournit les supports de cours numériques. Le Bénéficiaire est responsable de son matériel informatique (ordinateur portable recommandé).</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 5 — ATTESTATION</h3>
<p>Une attestation de participation sera remise au Bénéficiaire à l'issue de la formation, sous réserve d'une présence minimum de 80%.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 6 — ANNULATION</h3>
<p>Toute annulation moins de 48h avant le début de la formation entraîne la perte de l'acompte. En cas d'annulation par GOVIBE, l'intégralité des sommes versées est remboursée.</p>
".self::footer();
    }

    private static function tplDev(): string
    {
        return self::header('CONTRAT DE DÉVELOPPEMENT LOGICIEL')."
<p><strong>ENTRE LES SOUSSIGNÉS :</strong></p>
<p><strong>GOVIBE STARTUP LLC</strong>, agence de développement numérique, ci-après <em>\"le Prestataire\"</em> ;</p>
<p>ET <strong>{{client_name}}</strong>, ci-après <em>\"le Client\"</em>.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 1 — OBJET</h3>
<p>Le Prestataire s'engage à développer le projet <strong>{{project_title}}</strong> selon le cahier des charges annexé.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 2 — PÉRIMÈTRE (SCOPE)</h3>
<p>{{scope}}</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 3 — TARIF ET PAIEMENT</h3>
<p>Le budget total est de <strong>{{price}} {{currency}}</strong>.</p>
<p>Calendrier de paiement : {{payment_schedule}}</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 4 — DÉLAI DE LIVRAISON</h3>
<p>La livraison finale est prévue pour le <strong>{{delivery_date}}</strong>, sous réserve de la mise à disposition dans les délais des contenus et accès nécessaires par le Client.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 5 — PROPRIÉTÉ INTELLECTUELLE</h3>
<p>À complet paiement, le Client devient propriétaire du code source développé spécifiquement pour lui. Les frameworks, bibliothèques open-source et outils tiers restent sous leurs licences respectives.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 6 — GARANTIE</h3>
<p>Le Prestataire garantit le bon fonctionnement du livrable pendant 60 jours après réception sans charge. Au-delà, une prestation de maintenance peut être conclue séparément.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 7 — MODIFICATIONS EN COURS DE PROJET</h3>
<p>Tout ajout ou modification substantielle du périmètre fera l'objet d'un avenant signé des deux parties avant réalisation.</p>
".self::footer();
    }

    private static function tplNDA(): string
    {
        return self::header('ACCORD DE CONFIDENTIALITÉ (NDA)')."
<p><strong>ENTRE LES SOUSSIGNÉS :</strong></p>
<p><strong>GOVIBE STARTUP LLC</strong>, Port-au-Prince, Haïti, ci-après <em>\"GOVIBE\"</em> ;</p>
<p>ET <strong>{{partner_name}}</strong> ({{partner_type}}), ci-après <em>\"le Partenaire\"</em>.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 1 — OBJET</h3>
<p>Le présent accord a pour objet de définir les conditions dans lesquelles chaque partie peut communiquer à l'autre des informations confidentielles dans le cadre de leurs discussions commerciales ou techniques.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 2 — INFORMATIONS CONFIDENTIELLES</h3>
<p>Sont considérées comme confidentielles toutes informations techniques, commerciales, financières ou stratégiques échangées entre les parties, quel que soit le support (oral, écrit, numérique).</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 3 — OBLIGATIONS</h3>
<ul>
<li>Ne pas divulguer les informations confidentielles à des tiers sans accord préalable écrit</li>
<li>Utiliser les informations exclusivement dans le cadre de la collaboration envisagée</li>
<li>Protéger les informations avec le même niveau de soin que ses propres informations sensibles</li>
</ul>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 4 — EXCEPTIONS</h3>
<p>Les obligations de confidentialité ne s'appliquent pas aux informations déjà dans le domaine public, connues avant la divulgation, ou obtenues légalement d'un tiers.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 5 — DURÉE</h3>
<p>Le présent accord prend effet à la date de signature <strong>({{signing_date}})</strong> et reste en vigueur pendant <strong>{{duration_years}} an(s)</strong> après la fin des relations commerciales entre les parties.</p>

<h3 style='margin-top:1.5rem;font-size:1.1rem;font-weight:700;'>ARTICLE 6 — SANCTIONS</h3>
<p>Toute violation du présent accord engage la responsabilité civile de la partie fautive et peut donner lieu à des dommages et intérêts.</p>
".self::footer();
    }
}
