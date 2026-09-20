<?php

namespace App\Providers;

use App\Models\Territoire\Arrondissement;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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

        // La notification native pointe vers la route nommée "password.reset" —
        // la nôtre est préfixée "admin." pour rester cohérente avec le reste de
        // l'espace admin.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return route('admin.password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);
        });

        // Menu "Arrondissement Môle" (sections communales + communes) : présent
        // sur chaque page publique, partagé une seule fois ici plutôt que
        // rechargé dans chaque contrôleur.
        View::composer('layouts.public', function ($view) {
            $view->with('navArrondissement', Arrondissement::with(['communes.sectionsCommunales'])->first());
        });
    }
}
