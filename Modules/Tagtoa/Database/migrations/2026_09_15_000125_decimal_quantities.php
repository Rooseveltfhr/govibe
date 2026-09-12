<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — les quantités deviennent décimales.
 *
 * En Haïti, dans les Caraïbes et en Afrique de l'Ouest, une part énorme du
 * commerce ne se vend PAS à la pièce : le riz à la mamit et à la ti mamit, la
 * viande à la livre, l'huile au gode, le charbon au sac entamé.
 *
 * Tant que `qty` est un entier, vendre 2,5 livres de riz enregistre 2 : le
 * client paie moins que ce qu'il emporte, le stock ment d'une demi-livre à
 * chaque vente, et l'écart grandit tout seul. C'est une erreur d'argent, pas un
 * confort d'affichage — d'où cette migration AVANT toute interface qui propose
 * ces unités.
 *
 * Trois colonnes, la même précision (12,3) :
 *   • tagtoa_pos_sale_items.qty  — ce qui a été vendu
 *   • tagtoa_pos_products.stock  — ce qui reste côté caisse
 *   • tagtoa_menu_items.stock    — ce qui reste côté menu (même stock depuis B-3)
 *
 * MySQL exige un ALTER explicite. SQLite (tests, et certaines installations
 * modestes) n'applique pas la déclaration de type : une valeur 2,5 y est déjà
 * conservée telle quelle, donc rien à faire — mais on ne s'en remet pas au
 * hasard, on le dit.
 *
 * Aucune donnée n'est perdue : un entier est un décimal dont la partie
 * fractionnaire vaut zéro.
 */
return new class extends Migration
{
    /**
     * [table, colonne, nullable, non signé].
     *
     * `qty` reste NON SIGNÉ comme avant : une ligne de vente négative n'existe
     * pas, et la base doit continuer de le refuser. Le stock, lui, reste signé —
     * un commerce qui a vendu plus qu'il ne croyait avoir doit voir -3 plutôt
     * qu'un zéro rassurant et faux.
     */
    private const COLUMNS = [
        ['tagtoa_pos_sale_items', 'qty', false, true],
        ['tagtoa_pos_products', 'stock', true, false],
        ['tagtoa_menu_items', 'stock', true, false],
    ];

    public function up(): void
    {
        $this->retype('DECIMAL(12,3)', '1.000');
    }

    public function down(): void
    {
        // Retour à l'entier : les fractions déjà encaissées seraient tronquées
        // par la base. On l'accepte comme un retour arrière assumé, jamais comme
        // une opération courante.
        $this->retype('INT', '1');
    }

    private function retype(string $type, string $defaut): void
    {
        // Hors MySQL/MariaDB, le type déclaré n'est pas contraignant (SQLite) ou
        // la syntaxe diffère : on ne bricole pas un ALTER qu'on ne peut pas
        // vérifier ici.
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::COLUMNS as [$table, $colonne, $nullable, $nonSigne]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $colonne)) {
                continue;
            }

            $declaration = $type.($nonSigne ? ' UNSIGNED' : '');
            $declaration .= $nullable ? ' NULL' : " NOT NULL DEFAULT {$defaut}";

            DB::statement("ALTER TABLE `{$table}` MODIFY `{$colonne}` {$declaration}");
        }
    }
};
