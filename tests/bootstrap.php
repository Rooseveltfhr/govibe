<?php

/*
 * Bootstrap pour les tests UNITAIRES de logique pure (sans Laravel).
 * On charge directement les fichiers source dont les méthodes testées ne
 * dépendent pas du framework (Luhn, calcul de commission). Les `use` de
 * façades Laravel dans ces classes ne sont que des alias non résolus tant
 * qu'on n'appelle pas les méthodes qui les utilisent.
 */

require __DIR__.'/../vendor/autoload.php';

$base = __DIR__.'/../Modules/Tagtoa/app';

// Uniquement des classes sans parent Eloquent (sinon il faudrait charger Laravel).
require_once $base.'/Services/Loyalty/LoyaltyCardService.php';
require_once $base.'/Services/Billing/RevenueService.php';
require_once $base.'/Services/Notifications/NotificationService.php';
require_once $base.'/Services/Review/ReviewService.php';
require_once $base.'/Services/Inventory/StockService.php';
require_once $base.'/Services/Audit/AuditService.php';
require_once $base.'/Support/Money.php';
