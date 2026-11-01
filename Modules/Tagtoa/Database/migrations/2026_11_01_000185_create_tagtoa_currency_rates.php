<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA DEVISES (CUR-1) — devise par article et taux de change du marchand.
 *
 * - `tagtoa_currency_rates` : le taux EN VIGUEUR d'une devise (ISO ou personnalisée, ex.
 *   « $HT ») vers une devise principale, par commerce. 1 code = `rate` base.
 * - `tagtoa_currency_rate_history` : chaque changement de taux, en ajout seul (jamais modifié).
 * - `tagtoa_menu_items.currency` : NULL = devise du menu (rien ne change pour l'existant).
 * - `tagtoa_menu_order_items` : la ligne fige le prix d'origine, sa devise et le taux utilisé.
 *
 * Rejouable (chaque étape vérifie l'existant) et réversible. Index nommés courts (limite 64).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tagtoa_currency_rates')) {
            Schema::create('tagtoa_currency_rates', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 64);
                $table->string('code', 8);
                $table->string('base_currency', 8);
                $table->string('symbol', 8)->nullable();
                $table->unsignedTinyInteger('decimals')->default(2);
                $table->decimal('rate', 18, 6);
                $table->boolean('is_active')->default(true);
                $table->string('updated_by', 64)->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'code', 'base_currency'], 'cur_rates_tenant_code_base_uq');
            });
        }

        if (! Schema::hasTable('tagtoa_currency_rate_history')) {
            Schema::create('tagtoa_currency_rate_history', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 64);
                $table->unsignedBigInteger('rate_id');
                $table->string('code', 8);
                $table->string('base_currency', 8);
                $table->decimal('old_rate', 18, 6)->nullable();
                $table->decimal('new_rate', 18, 6)->nullable();
                $table->string('event', 20)->default('rate');
                $table->string('changed_by', 64)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['tenant_id', 'rate_id'], 'cur_hist_tenant_rate_idx');
            });
        }

        if (Schema::hasTable('tagtoa_menu_items') && ! Schema::hasColumn('tagtoa_menu_items', 'currency')) {
            Schema::table('tagtoa_menu_items', function (Blueprint $table) {
                $table->string('currency', 8)->nullable()->after('price');
            });
        }

        if (Schema::hasTable('tagtoa_menu_order_items') && ! Schema::hasColumn('tagtoa_menu_order_items', 'currency_original')) {
            Schema::table('tagtoa_menu_order_items', function (Blueprint $table) {
                $table->string('currency_original', 8)->nullable();
                $table->decimal('unit_price_original', 14, 2)->nullable();
                $table->decimal('rate_used', 18, 6)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tagtoa_menu_order_items') && Schema::hasColumn('tagtoa_menu_order_items', 'currency_original')) {
            Schema::table('tagtoa_menu_order_items', function (Blueprint $table) {
                $table->dropColumn(['currency_original', 'unit_price_original', 'rate_used']);
            });
        }
        if (Schema::hasTable('tagtoa_menu_items') && Schema::hasColumn('tagtoa_menu_items', 'currency')) {
            Schema::table('tagtoa_menu_items', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
        Schema::dropIfExists('tagtoa_currency_rate_history');
        Schema::dropIfExists('tagtoa_currency_rates');
    }
};
