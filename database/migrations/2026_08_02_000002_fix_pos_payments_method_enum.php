<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MODIFY COLUMN ... ENUM est de la syntaxe MySQL. Exécutée telle quelle
     * sur SQLite — la base des tests — elle échoue et emporte toute migration
     * qui suit, ce qui rendait impossible le moindre test utilisant la base.
     *
     * SQLite n'a pas de type ENUM : la colonne y est un texte libre, la liste
     * des valeurs est déjà portée par la validation applicative. Il n'y a donc
     * rien à modifier, et sauter l'instruction laisse le schéma correct.
     */
    public function up(): void
    {
        $this->modifierEnum("'cash','moncash','natcash','bank_transfer','card','paypal','zelle','usdt'");
    }

    public function down(): void
    {
        $this->modifierEnum("'cash','moncash','natcash','bank_transfer','card','paypal'");
    }

    private function modifierEnum(string $valeurs): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (! Schema::hasTable('pos_payments')) {
            return;
        }

        DB::statement(
            "ALTER TABLE pos_payments MODIFY COLUMN method ENUM({$valeurs}) NOT NULL DEFAULT 'cash'"
        );
    }
};
