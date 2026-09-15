<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kòmand yon ajan.
 *
 * De chemen mennen isit la: yon machann ki vle nou monte ajan an pou li
 * (`mode = expert`), oswa yon machann ki monte l li menm men ki vle nou
 * konekte l sou WhatsApp / sit li (`mode = self`). Nan de ka yo, sa ki
 * enpòtan se yon fason pou nou rele moun nan — se poutèt sa WhatsApp
 * obligatwa epi imèl la opsyonèl: an Ayiti se WhatsApp ki mache.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_orders', function (Blueprint $table): void {
            $table->id();

            // Referans lan se sa moun nan site nan mesaj WhatsApp li — li
            // dwe ka li nan yon vwa, kidonk kout epi san karaktè ki twonpe.
            $table->string('reference', 20)->unique();

            $table->string('sector', 40);          // modèl ki chwazi
            $table->string('business_name', 160);
            $table->string('contact_name', 120)->nullable();
            $table->string('whatsapp', 40);
            $table->string('email', 160)->nullable();

            $table->string('mode', 20)->default('expert');   // expert | self
            $table->json('channels')->nullable();            // whatsapp, website, phone
            $table->text('notes')->nullable();

            $table->string('status', 20)->default('nouvo');  // nouvo | an_kou | fèt | anile
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_orders');
    }
};
