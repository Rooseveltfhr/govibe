<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue des moyens de paiement proposés aux clients.
 *
 * GOVIBE n'encaisse pas en ligne : le client choisit une passerelle, voit les
 * coordonnées à utiliser, paie de son côté, puis l'équipe rapproche. Les
 * champs décrivent donc « où envoyer l'argent », pas une transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passerelles_paiement', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code')->unique();

            // Détermine ce que la fiche publique met en avant :
            // un numéro à recopier, un lien à suivre, une adresse à copier.
            $table->enum('type', ['mobile_money', 'banque', 'transfert', 'lien', 'crypto'])
                  ->default('mobile_money');

            $table->string('titulaire')->nullable();
            $table->string('numero_compte')->nullable();
            // TRC20, BEP20, Bitcoin — vide hors crypto.
            $table->string('reseau')->nullable();
            $table->string('lien_paiement')->nullable();
            $table->text('instructions')->nullable();

            $table->string('qr_code')->nullable();
            $table->string('logo')->nullable();

            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();

            $table->index(['actif', 'ordre']);
        });

        // Les coordonnées fournies par Roosevelt. Le titulaire de MonCash et
        // NatCash est laissé vide : il n'a été communiqué que partiellement
        // (« Dani***** pha**** »), et afficher des astérisques sur une page de
        // paiement empêcherait le client de vérifier à qui il envoie l'argent.
        // À compléter depuis l'ERP.
        $maintenant = now();
        $lignes = [
            [
                'nom' => 'MonCash', 'code' => 'moncash', 'type' => 'mobile_money',
                'numero_compte' => '34420793', 'ordre' => 1,
                'instructions' => "Envoyez le montant au numéro MonCash ci-dessus, puis transmettez la capture de confirmation à l'équipe GOVIBE.",
            ],
            [
                'nom' => 'NatCash', 'code' => 'natcash', 'type' => 'mobile_money',
                'numero_compte' => '43709798', 'ordre' => 2,
                'instructions' => "Envoyez le montant au numéro NatCash ci-dessus, puis transmettez la capture de confirmation à l'équipe GOVIBE.",
            ],
            [
                'nom' => 'Unibank Online', 'code' => 'unibank', 'type' => 'banque',
                'ordre' => 3,
                'instructions' => "Virement depuis Unibank Online. Le numéro de compte est à renseigner par l'administration.",
            ],
            [
                'nom' => 'Zelle', 'code' => 'zelle', 'type' => 'transfert',
                'titulaire' => 'Rood-veltsen Forestal', 'ordre' => 4,
                'instructions' => "Envoyez via Zelle au nom indiqué. L'email ou le téléphone associé est à renseigner par l'administration.",
            ],
            [
                'nom' => 'PayPal', 'code' => 'paypal', 'type' => 'lien',
                'lien_paiement' => 'https://www.paypal.com/ncp/payment/EVEPJAPWQ4U9W',
                'ordre' => 5,
                'instructions' => "Paiement immédiat par carte ou compte PayPal.",
            ],
            [
                'nom' => 'USDT (TRC20)', 'code' => 'usdt_trc20', 'type' => 'crypto',
                'reseau' => 'TRC20 — Tron',
                'numero_compte' => 'TL7S1EkVVrVqLsb5rQaZ4vkgJ55KaPYf5s',
                'ordre' => 6,
                'instructions' => "Réseau TRC20 uniquement. Un envoi sur un autre réseau est définitivement perdu.",
            ],
            [
                'nom' => 'USDT (BEP20)', 'code' => 'usdt_bep20', 'type' => 'crypto',
                'reseau' => 'BEP20 — BNB Smart Chain',
                'numero_compte' => '0xa2a19cb6c4d26e0c62c97ee6c5c9c1147d9f67eb',
                'ordre' => 7,
                'instructions' => "Réseau BEP20 uniquement. Un envoi sur un autre réseau est définitivement perdu.",
            ],
            [
                'nom' => 'Bitcoin', 'code' => 'bitcoin', 'type' => 'crypto',
                'reseau' => 'Bitcoin',
                'numero_compte' => '126GgMXcKgaqAf4AczSJdJZGKnAK9acnKn',
                'ordre' => 8,
                'instructions' => "Réseau Bitcoin. Vérifiez l'adresse caractère par caractère avant d'envoyer.",
            ],
        ];

        foreach ($lignes as $ligne) {
            DB::table('passerelles_paiement')->insert($ligne + [
                'actif'      => true,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('passerelles_paiement');
    }
};
