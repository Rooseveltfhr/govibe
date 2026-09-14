<?php

namespace Modules\Tagtoa\Tests\Stubs;

use Illuminate\Support\ServiceProvider;
use Modules\Tagtoa\App\Console\BackfillOrdersCommand;
use Modules\Tagtoa\App\Console\MintStandsCommand;

/**
 * Enregistre les commandes de console du module pour les tests.
 *
 * En production c'est TagtoaServiceProvider qui le fait. Ce provider-là n'est
 * pas bootable ici — il dépend de `module_path()` et surcharge des vues du
 * cœur Biztap, absent de ce dépôt. On reproduit donc UNIQUEMENT la partie
 * console, et un test de garde vérifie que les deux listes restent identiques :
 * une commande ajoutée en production et oubliée ici ne serait jamais testée.
 */
class ConsoleProvider extends ServiceProvider
{
    /** Doit refléter TagtoaServiceProvider::registerCommands(). */
    public const COMMANDS = [
        BackfillOrdersCommand::class,
        MintStandsCommand::class,
    ];

    public function boot(): void
    {
        $this->commands(self::COMMANDS);
    }
}
