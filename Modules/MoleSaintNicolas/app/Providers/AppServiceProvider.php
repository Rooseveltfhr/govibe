<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Hébergement mutualisé : certaines instances MySQL/MariaDB créent encore
        // les tables en row_format non-dynamique, où une clé varchar(255) en
        // utf8mb4 (1020 octets) dépasse la limite de clé InnoDB de 1000 octets.
        Schema::defaultStringLength(191);
    }
}
