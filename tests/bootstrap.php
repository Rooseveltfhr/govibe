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
require_once $base.'/Services/Event/StaffPinService.php';
require_once $base.'/Services/Event/SyncReconciler.php';
require_once $base.'/Services/Event/TicketImportService.php';
require_once $base.'/Support/Store/Cart.php';
require_once $base.'/Support/Gateways/MonCash.php';
require_once $base.'/Support/Gateways/PayPal.php';
require_once $base.'/Support/Gateways/CoinPayments.php';
require_once $base.'/Support/Gateways/Stripe.php';
require_once $base.'/Support/Card/CardWallet.php';
require_once $base.'/Support/Event/Ledger.php';
require_once $base.'/Support/Event/EventDays.php';
require_once $base.'/Support/Menu/ItemOptionPricing.php';
require_once $base.'/Support/Menu/BusinessProfile.php';
require_once $base.'/Support/PaymentGateway.php';
require_once $base.'/Support/Pay/GatewayCatalog.php';
require_once $base.'/Support/Api/ApiToken.php';
require_once $base.'/Support/Pay/GatewayCredentialFields.php';
require_once $base.'/Support/Money.php';
require_once $base.'/Support/DashboardModules.php';
require_once $base.'/Support/Dev/RouteNames.php';
require_once $base.'/Support/Nfc/AesCmac.php';
require_once $base.'/Support/Nfc/Ntag424.php';
require_once $base.'/Support/Nfc/SunVerifier.php';
