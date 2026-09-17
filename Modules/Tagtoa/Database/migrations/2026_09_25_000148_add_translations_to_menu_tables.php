<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — une traduction par langue, jamais un menu par langue.
 *
 * Une seule colonne JSON par table, plutôt qu'une table `translations`
 * séparée (menu_id, locale, field, value) : le menu digital TAGTOA sert des
 * pages sous forte charge (chaque scan d'un stand redirige ici), et une
 * jointure de plus sur la requête la plus fréquente de la plateforme se paie à
 * chaque affichage. Le JSON se lit avec la ligne elle-même, sans jointure.
 *
 * Forme : `{"en": {"tagline": "...", "description": "..."}}` pour le menu ;
 * `{"en": {"name": "..."}}` pour une catégorie ; `{"en": {"name": "...",
 * "description": "..."}}` pour un article. Voir Translatable::resolve().
 *
 * Nullable, sans valeur par défaut : une carte déjà publiée n'a AUCUNE
 * traduction, et doit continuer de s'afficher exactement comme avant.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'tagtoa_menus'           => 'alias',
            'tagtoa_menu_categories' => 'sort',
            'tagtoa_menu_items'      => 'badge',
        ];

        foreach ($tables as $table => $apres) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'translations')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($apres) {
                $t->json('translations')->nullable()->after($apres);
            });
        }
    }

    public function down(): void
    {
        foreach (['tagtoa_menus', 'tagtoa_menu_categories', 'tagtoa_menu_items'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'translations')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('translations'));
            }
        }
    }
};
