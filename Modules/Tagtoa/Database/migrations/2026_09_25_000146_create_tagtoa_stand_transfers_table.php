<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA SMART STAND — la cession.
 *
 * ── Le problème, tel qu'il se pose réellement ───────────────────────────
 *
 * Un bar change de patron. Un restaurant est vendu. Une personne meurt et sa
 * fille reprend l'affaire. Ce n'est pas un cas rare à prévoir « un jour » :
 * dans le commerce haïtien, un établissement change de main plus souvent qu'il
 * ne change d'enseigne.
 *
 * Jusqu'ici, TAGTOA n'avait aucun chemin pour ça. Un stand réclamé est ACTIF,
 * et `isClaimable()` est faux pour toujours : l'objet devenait un presse-papier
 * au moment précis où l'affaire changeait de propriétaire. Quarante stands
 * morts le jour de la vente du restaurant.
 *
 * ── Pourquoi PAS un simple « libérer » ──────────────────────────────────
 *
 * La solution évidente serait de rendre le stand à l'état non réclamé : le
 * nouveau propriétaire gratte et réclame, comme au premier jour.
 *
 * Elle est fausse, et dangereusement. Le panneau à gratter a DÉJÀ été gratté
 * par le premier propriétaire — c'est ce qui lui a donné le stand. Le secret
 * est donc écrit en clair sur l'objet, et l'ancien patron l'a peut-être
 * photographié. Rendre ce secret vivant, c'est lui donner le pouvoir de
 * reprendre les quarante stands du bar qu'il vient de vendre, et de rediriger
 * tous les QR posés sur les tables vers le menu de son nouvel établissement.
 * Le client attablé chez l'acheteur commanderait chez le vendeur.
 *
 * D'où la règle de ce module, qui ne se négocie pas :
 *
 *   UN STAND ACTIVÉ NE REDEVIENT JAMAIS RÉCLAMABLE.
 *   La seule sortie d'un commerce est une cession dirigée.
 *
 * ── Ce que cette table décrit ───────────────────────────────────────────
 *
 * Une OFFRE : « je cède ces N stands, à qui présentera ce code ». Une offre,
 * un code, N stands — parce qu'on vend un restaurant, pas un chevalet. Quarante
 * codes à recopier un par un, personne ne le fait.
 *
 * Le code est au PORTEUR, à dessein : l'acheteur n'a souvent pas encore de
 * compte TAGTOA au moment où l'affaire se conclut. Exiger qu'il en ait un
 * avant de recevoir l'offre bloquerait la cession sur une formalité. Le risque
 * du porteur est tenu autrement : durée courte, usage unique, annulation
 * possible à tout instant par le cédant, et tout est journalisé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tagtoa_stand_transfers')) {
            Schema::create('tagtoa_stand_transfers', function (Blueprint $t) {
                $t->id();

                // Le cédant et le repreneur.
                //
                // `from_business_id` / `to_business_id`, et NON `tenant_id` :
                // une cession a DEUX côtés. Une colonne `tenant_id` promettrait
                // une isolation automatique par un seul commerce — et cacherait
                // alors au repreneur l'offre qui lui est destinée, ou au cédant
                // l'historique de ce qu'il a cédé. Même leçon qu'au revendeur :
                // le nom de la colonne est un contrat, pas une étiquette.
                $t->string('from_business_id', 64)->index();
                $t->string('to_business_id', 64)->nullable()->index();

                // Le code de cession, HACHÉ. Le clair n'entre jamais en base :
                // il est montré une fois au cédant, puis oublié. Perdu, on
                // annule et on réémet — c'est une seconde, et c'est la seule
                // façon honnête de tenir la promesse « personne d'autre ne
                // peut le lire », fuite de la base comprise.
                //
                // sha256 et non bcrypt : le code est tiré au hasard sur 60 bits,
                // donc hors de portée d'un dictionnaire, et il faut pouvoir le
                // RETROUVER par sa seule saisie. Un hachage lent interdirait la
                // recherche indexée et obligerait à parcourir toutes les offres.
                $t->string('code_hash', 64)->unique();

                // Ce que le cédant a écrit pour se souvenir : « vente du bar à
                // Jean-Claude ». Jamais montré au repreneur.
                $t->string('note', 160)->nullable();

                $t->timestamp('expires_at')->index();
                $t->timestamp('accepted_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();

                // Qui a proposé, et depuis où. C'est ce qui tranche un litige
                // six mois plus tard : « je n'ai jamais cédé mes stands ».
                $t->string('actor_name', 120)->nullable();
                $t->string('ip', 45)->nullable();

                $t->timestamps();
            });
        }

        if (! Schema::hasTable('tagtoa_stand_transfer_items')) {
            Schema::create('tagtoa_stand_transfer_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('transfer_id')->index();
                $t->unsignedBigInteger('stand_id')->index();

                // Un stand ne peut être dans deux offres à la fois : sinon deux
                // codes circulent pour le même objet, et le second repreneur
                // découvre qu'il a acheté un stand déjà parti. La contrainte
                // double l'état `transfer_pending`, qui dit déjà la même chose —
                // deux verrous, parce qu'un seul finit toujours par s'oublier
                // dans un chemin nouveau.
                $t->unique(['transfer_id', 'stand_id']);

                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_stand_transfer_items');
        Schema::dropIfExists('tagtoa_stand_transfers');
    }
};
