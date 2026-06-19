<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — Articles vendus in-event (buvette, stands…)
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('emoji', 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_sale_items');
    }
};
