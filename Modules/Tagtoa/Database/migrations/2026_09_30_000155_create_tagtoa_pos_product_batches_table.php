<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — les lots, pour les commerces qui vendent des médicaments.
 *
 * Une pharmacie ne peut pas se contenter d'un stock global : deux
 * réassorts du même médicament ont presque toujours deux dates de
 * péremption différentes, et vendre « le plus vieux d'abord » suppose de
 * savoir lequel c'est. `purchased_at` (une seule date, globale à
 * l'article) ne le permettait pas.
 *
 * `product_id` : SANS contrainte de clé étrangère, comme toutes les
 * relations POS de ce module (category_id, supplier_id, parent_product_id)
 * — supprimer un article ne doit jamais échouer à cause de ses lots.
 *
 * Le stock du produit continue de passer par StockLedger, inchangé : un
 * lot est une couche de traçabilité EN PLUS, jamais un second compteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_pos_product_batches', function (Blueprint $t) {
            $t->id();
            $t->string('tenant_id', 64);
            $t->unsignedBigInteger('product_id');
            $t->decimal('quantity', 12, 3);
            // Nullable : un commerce peut vouloir tracer un lot (numéro de
            // réception) sans nécessairement suivre de péremption pour cet
            // article — mais c'est ce champ que l'écran « Péremption » lit.
            $t->date('expires_at')->nullable();
            $t->date('received_at')->nullable();
            $t->string('note', 160)->nullable();
            $t->timestamps();

            // La requête la plus fréquente : les lots d'UN commerce, les plus
            // proches de périmer en premier.
            $t->index(['tenant_id', 'expires_at']);
            $t->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_pos_product_batches');
    }
};
