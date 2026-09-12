<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * TAGTOA Pay — les moyens de paiement passent du niveau PAGE au niveau MARCHAND.
 *
 * Avant : chaque lien de paiement portait ses propres méthodes, donc le marchand
 * ressaisissait son numéro MonCash à chaque nouveau lien. Après : il configure
 * ses moyens UNE FOIS, et tous ses liens les utilisent.
 *
 * Migration NON DESTRUCTIVE — aucune ligne n'est supprimée, car les preuves de
 * paiement sont supprimées en cascade depuis les méthodes : effacer une méthode
 * effacerait l'historique des paiements qu'elle a reçus.
 *
 * Pour chaque marchand :
 *   1. on crée sa page « bibliothèque » (invisible, jamais publique) ;
 *   2. pour chaque type de méthode, la ligne la mieux renseignée est RATTACHÉE
 *      à cette bibliothèque (simple mise à jour de payment_page_id) ;
 *   3. les doublons du même type restent en base, rattachés à leur page
 *      d'origine, mais désactivés — leurs preuves restent donc intactes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_payment_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_payment_methods', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->index()->after('payment_page_id');
            }
        });

        if (! Schema::hasColumn('tagtoa_payment_pages', 'is_library')) {
            return; // migration précédente non appliquée : on ne tente rien
        }

        // Renseigne tenant_id sur les méthodes existantes, depuis leur page.
        foreach (DB::table('tagtoa_payment_pages')->select('id', 'tenant_id')->get() as $page) {
            DB::table('tagtoa_payment_methods')
                ->where('payment_page_id', $page->id)
                ->update(['tenant_id' => $page->tenant_id]);
        }

        // Un marchand = une bibliothèque. On traite aussi tenant_id NULL
        // (installations mono-marchand) pour ne laisser personne de côté.
        $tenants = DB::table('tagtoa_payment_pages')
            ->where('is_library', false)
            ->distinct()->pluck('tenant_id');

        foreach ($tenants as $tenantId) {
            $libraryId = $this->libraryPageId($tenantId);

            $methods = DB::table('tagtoa_payment_methods')
                ->where('payment_page_id', '!=', $libraryId)
                ->when($tenantId === null,
                    fn ($q) => $q->whereNull('tenant_id'),
                    fn ($q) => $q->where('tenant_id', $tenantId))
                ->orderByDesc('is_active')
                ->orderByDesc('updated_at')
                ->get();

            $kept = [];
            foreach ($methods as $method) {
                if (isset($kept[$method->type])) {
                    // Doublon : conservé (ses preuves y sont rattachées) mais retiré
                    // de l'affichage — la bibliothèque fait désormais autorité.
                    DB::table('tagtoa_payment_methods')
                        ->where('id', $method->id)->update(['is_active' => false]);

                    continue;
                }

                $kept[$method->type] = true;
                DB::table('tagtoa_payment_methods')
                    ->where('id', $method->id)
                    ->update(['payment_page_id' => $libraryId]);
            }
        }
    }

    /** Crée (ou retrouve) la page bibliothèque d'un marchand. */
    private function libraryPageId(?string $tenantId): int
    {
        $existing = DB::table('tagtoa_payment_pages')
            ->where('is_library', true)
            ->when($tenantId === null,
                fn ($q) => $q->whereNull('tenant_id'),
                fn ($q) => $q->where('tenant_id', $tenantId))
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        do {
            $alias = 'lib-'.Str::lower(Str::random(18));
        } while (DB::table('tagtoa_payment_pages')->where('alias', $alias)->exists());

        return (int) DB::table('tagtoa_payment_pages')->insertGetId([
            'tenant_id'        => $tenantId,
            'title'            => 'Moyens de paiement',
            'alias'            => $alias,
            'default_currency' => 'HTG',
            'is_active'        => false, // jamais publique
            'is_library'       => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        // On ne défait pas le regroupement : les méthodes resteraient valides,
        // mais on ne saurait pas à quelle page d'origine les réattacher.
        Schema::table('tagtoa_payment_methods', function (Blueprint $table) {
            if (Schema::hasColumn('tagtoa_payment_methods', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }
};
