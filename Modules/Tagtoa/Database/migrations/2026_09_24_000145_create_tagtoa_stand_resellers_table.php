<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA SMART STAND — le revendeur.
 *
 * ── Pourquoi un revendeur, et pas une vente directe ─────────────────────
 *
 * TAGTOA ne peut pas livrer un stand à un restaurant de Cap-Haïtien depuis
 * Port-au-Prince pour douze dollars. Le matériel se vend comme se vend toute
 * marchandise en Haïti : par des gens qui connaissent leur zone, tiennent un
 * carton chez eux, et le vendent de la main à la main.
 *
 * ── LA LIGNE QUI NE BOUGE PAS ───────────────────────────────────────────
 *
 * Le revendeur déplace l'OBJET. Il ne touche JAMAIS à l'identité numérique.
 *
 * Concrètement : il détient des cartons, il déclare ses ventes, et c'est tout.
 * Il ne voit aucun code d'activation — ces codes sont sous un panneau à
 * gratter, et c'est justement leur point : celui qui tient l'objet est le seul
 * à pouvoir les lire. Un revendeur qui verrait les codes de son stock pourrait
 * réclamer cinquante stands à son nom, ou les revendre deux fois.
 *
 * C'est pour cela que ce module ne crée AUCUN champ portant un secret, et que
 * la console du revendeur est construite autour de cette absence.
 *
 * ── Le revendeur est un commerce ────────────────────────────────────────
 *
 * Il a déjà un compte TAGTOA — beaucoup de revendeurs sont eux-mêmes des
 * marchands. On raccroche donc la fiche à son commerce plutôt que d'inventer
 * un second système de connexion : un mot de passe de plus est un mot de passe
 * qu'on écrit sur un papier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_stand_resellers')) {
            return;
        }

        Schema::create('tagtoa_stand_resellers', function (Blueprint $t) {
            $t->id();
            // Le commerce qui EST ce revendeur.
            //
            // `business_id` et NON `tenant_id`, délibérément : ce n'est pas
            // « à quel commerce appartient cette ligne » mais « quel commerce
            // est ce revendeur ». Une colonne `tenant_id` promettrait une
            // isolation automatique que cette table ne doit PAS avoir — le
            // fondateur doit tous les voir pour leur affecter des cartons.
            //
            // Unique : un commerce ne peut pas être deux revendeurs, sinon son
            // stock se dédoublerait.
            $t->string('business_id', 64)->unique();
            $t->string('name', 120);
            $t->string('contact_phone', 40)->nullable();
            // La zone qu'il couvre. Sert au fondateur pour savoir à qui
            // affecter un carton, et au marchand pour savoir qui appeler.
            $t->string('zone', 80)->nullable();
            // Ce qu'il garde sur chaque stand vendu. Informatif à ce stade :
            // le règlement se fait hors application, et prétendre le contraire
            // avant d'avoir construit le règlement serait mentir à l'écran.
            $t->decimal('commission_pct', 5, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->string('notes', 255)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_stand_resellers');
    }
};
