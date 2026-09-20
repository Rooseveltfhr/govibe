<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — un employé peut opérer l'écran cuisine du MENU sans que ça change
 * son rôle POS.
 *
 * `Staff::can()` s'appuie sur `StaffAccess` (3 rôles, 12 droits — tous POS :
 * catalogue, caisse, remise). Un « rôle cuisine » n'a rien à voir avec ce
 * vocabulaire, et le réutiliser via la colonne `role` existante ferait tomber
 * un employé « cuisine » sur le rôle inconnu ⇒ `cashier` par défaut
 * (StaffService::save()) — il hériterait alors d'aptitudes de caisse (encaisser,
 * retirer du panier) qu'on ne voulait jamais lui donner. D'où une colonne à
 * part, un axe indépendant du rôle POS : un caissier ou un gérant peut AUSSI
 * cocher cette case sans perdre son rôle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_staff', function (Blueprint $t) {
            $t->boolean('is_kitchen')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_staff', function (Blueprint $t) {
            $t->dropColumn('is_kitchen');
        });
    }
};
