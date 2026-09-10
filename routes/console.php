<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notify:reminders')->dailyAt('08:00');
Schedule::command('birthday:wishes')->dailyAt('09:00');

// Facturation des abonnements. Tôt le matin : les factures partent avant que
// l'équipe et les clients ne commencent leur journée.
Schedule::command('abonnements:facturer')
    ->dailyAt('06:00')
    ->withoutOverlapping();
