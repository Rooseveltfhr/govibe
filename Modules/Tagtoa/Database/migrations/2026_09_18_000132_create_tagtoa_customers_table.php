<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — les clients du commerce.
 *
 * IMPORTANT : une fiche client est OPTIONNELLE, et le restera.
 *
 * La majorité des ventes d'un commerce de quartier sont anonymes : quelqu'un
 * entre, achète un pâté, repart. Lui demander son nom pour encaisser serait
 * absurde, et une caisse qui l'exige est une caisse qu'on n'utilise pas. Une
 * commande sans client est donc parfaitement normale — c'est même le cas le
 * plus fréquent au comptoir.
 *
 * La fiche sert quand le client la veut : un habitué qu'on rappelle, une
 * entreprise qui exige une facture à son nom et à son NIF, une livraison à
 * une adresse. C'est là que l'historique et la relance deviennent possibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_customers', function (Blueprint $table) {
            $table->id();

            // Le COMMERCE : le carnet d'adresses de la boulangerie n'est pas
            // celui du bar, même si c'est le même patron.
            $table->string('tenant_id')->index();

            // Nullable : un client peut n'avoir laissé qu'un téléphone.
            $table->string('name', 160)->nullable();
            $table->string('phone', 40)->nullable();

            // Clé de rapprochement : les huit derniers chiffres.
            //
            // « 3712-4455 », « +509 3712 4455 » et « 37124455 » sont le même
            // client. Sans clé commune il aurait trois fiches et aucun
            // historique — ce qui vide de son sens l'idée même d'un carnet.
            //
            // Huit chiffres parce que c'est la longueur d'un numéro haïtien.
            // Deux numéros de pays différents partageant leurs huit derniers
            // chiffres seraient confondus : dans le carnet d'UN commerce de
            // quartier le risque est théorique, alors que les trois fiches
            // pour un même habitué sont une certitude.
            $table->string('phone_key', 16)->nullable()->index();
            $table->string('email', 160)->nullable();
            $table->string('address', 240)->nullable();

            // Numéro fiscal DU CLIENT : une entreprise qui achète exige que sa
            // facture le porte, sinon elle ne peut pas la passer en charge.
            $table->string('tax_number', 40)->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Le téléphone identifie un client dans la pratique haïtienne :
            // c'est par lui qu'on le retrouve, pas par un numéro de compte.
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'phone_key']);
            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_customers');
    }
};
