<?php

namespace Modules\Tagtoa\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tagtoa\App\Models\Booking\Booking;
use Modules\Tagtoa\App\Models\Booking\BookingPage;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Links\Link;
use Modules\Tagtoa\App\Models\Links\LinkPage;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Site\Site;
use Modules\Tagtoa\App\Services\Loyalty\LoyaltyCardService;

/**
 * TAGTOA — données de démo pour DEVEXPO.
 *
 *   php artisan db:seed --class="Modules\Tagtoa\Database\Seeders\TagtoaDemoSeeder"
 *
 * Idempotent (utilise firstOrCreate sur les alias). tenant_id volontairement null
 * pour une démo simple — adapter si nécessaire.
 */
class TagtoaDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1) PAY — page MonCash + NatCash
        $pay = PaymentPage::firstOrCreate(
            ['alias' => 'demo'],
            ['title' => 'Payez TAGTOA Demo', 'default_currency' => 'HTG', 'is_active' => true]
        );
        $demoMethods = [
            ['moncash',     '+509 3000 0000'],
            ['natcash',     '+509 4000 0000'],
            ['unibank',     'Unibank • 011-xxxxxxx'],
            ['sogebank',    'Sogebank • 021-xxxxxxx'],
            ['bnc',         'BNC • 031-xxxxxxx'],
            ['zelle',       'pay@tagtoa.com'],
            ['cashapp',     '$tagtoa'],
            ['paypal',      'paypal@tagtoa.com'],
            ['card',        'VISA / Mastercard'],
            ['usdt',        'TRC20 • Txxxxxxxxxxxxxxxx'],
            ['btc',         'bc1qxxxxxxxxxxxxxxxx'],
            ['eth',         '0xXXXXXXXXXXXXXXXX'],
            ['cash',        'Sou plas'],
            ['tagtoa_card', 'Carte TAGTOA'],
        ];
        $needsProof = ['moncash', 'natcash', 'unibank', 'sogebank', 'bnc', 'zelle', 'cashapp', 'paypal', 'usdt', 'btc', 'eth'];
        foreach ($demoMethods as $i => [$type, $acct]) {
            PaymentMethod::firstOrCreate(
                ['payment_page_id' => $pay->id, 'type' => $type],
                ['account_holder' => 'TAGTOA Demo', 'account_number' => $acct, 'requires_proof' => in_array($type, $needsProof, true), 'is_active' => true, 'sort' => $i]
            );
        }

        // 2) LOYALTY — programme + 1 carte
        $program = Program::firstOrCreate(
            ['alias' => 'demo-fidelite'],
            ['name' => 'TAGTOA Fidélité', 'points_per_dollar' => 1, 'dollar_per_point' => 0.01, 'currency' => 'HTG', 'is_active' => true]
        );
        $program->rewards()->firstOrCreate(
            ['name' => 'Café offert'],
            ['points_required' => 100, 'discount_value' => 100, 'discount_type' => 'fixed', 'is_active' => true]
        );
        if ($program->cards()->count() === 0) {
            app(LoyaltyCardService::class)->issueCard($program, ['cardholder_name' => 'Client Demo', 'balance' => 250]);
        }

        // 3) LINKS — page Linktree
        $links = LinkPage::firstOrCreate(
            ['alias' => 'demo-links'],
            ['title' => 'TAGTOA Demo', 'bio' => 'Tout sur un seul lien.', 'theme' => 'blue', 'pay_page_id' => $pay->id, 'donation_label' => 'Soutiens-nous', 'is_active' => true]
        );
        foreach ([
            ['Instagram', 'https://instagram.com/tagtoa'],
            ['WhatsApp', 'https://wa.me/50900000000'],
            ['Site web', 'https://tagtoa.com'],
        ] as $i => [$label, $url]) {
            $links->links()->firstOrCreate(['url' => $url], [
                'label' => $label, 'platform' => Link::detectPlatform($url), 'sort' => $i, 'is_active' => true,
            ]);
        }

        // 4) EVENT — concert démo (gratuit) + 1 type de billet
        $event = Event::firstOrCreate(
            ['alias' => 'demo-concert'],
            ['title' => 'TAGTOA Live Demo', 'type' => 'concert', 'venue' => 'BANJ, Gonaïves',
             'starts_at' => now()->addDays(7)->setTime(19, 0), 'currency' => 'HTG', 'is_free' => true, 'is_published' => true]
        );
        $event->ticketTypes()->firstOrCreate(['name' => 'Standard'], ['price' => 0, 'quantity' => 200, 'is_active' => true]);

        // 5) MENU — menu digital démo (lounge/restaurant) + catégories + produits
        $menu = Menu::firstOrCreate(
            ['alias' => 'demo-menu'],
            [
                'name' => 'TAGTOA Lounge Demo', 'type' => 'lounge',
                'tagline' => 'Cuisine créole • Cocktails • Ambiance lounge',
                'description' => 'Scannez, commandez, payez. Le menu digital TAGTOA.',
                'currency' => 'HTG', 'whatsapp' => '+509 3000 0000',
                'pay_page_id' => $pay->id, 'accent_color' => '#16A34A', 'theme' => 'dark',
                'show_prices' => true, 'ordering_enabled' => true, 'is_active' => true,
            ]
        );
        $menuData = [
            ['Entrées', '🥗', [
                ['Accra de morue', 250, '🧆', 'Beignets de morue épicés', 'Populaire'],
                ['Pikliz maison', 100, '🌶️', 'Chou et carottes pimentés', null],
            ]],
            ['Plats', '🍽️', [
                ['Griot + bannann peze', 650, '🍖', 'Porc mariné, banane plantain', 'Populaire'],
                ['Poisson gros sel', 800, '🐟', 'Poisson frit, riz djon-djon', null],
                ['Tassot kabrit', 750, '🥩', 'Cabri frit, sauce ti-malice', 'Nouveau'],
            ]],
            ['Boissons', '🍹', [
                ['Prestige', 150, '🍺', 'Bière nationale', null],
                ['Rhum Barbancourt', 300, '🥃', '5 étoiles, sur glace', null],
                ['Jus de fruits frais', 150, '🧃', 'Chadèque, corossol, grenadia', null],
            ]],
        ];
        foreach ($menuData as $ci => [$catName, $icon, $items]) {
            $cat = $menu->categories()->firstOrCreate(['name' => $catName], ['icon' => $icon, 'sort' => $ci, 'is_active' => true]);
            foreach ($items as $ii => [$name, $price, $emoji, $desc, $badge]) {
                $cat->items()->firstOrCreate(
                    ['name' => $name],
                    ['menu_id' => $menu->id, 'price' => $price, 'emoji' => $emoji, 'description' => $desc, 'badge' => $badge, 'is_available' => true, 'sort' => $ii]
                );
            }
        }

        // 5b) MENU — une commande démo (en attente) pour tester la gestion
        if ($menu->orders()->count() === 0) {
            $griot = $menu->items()->where('name', 'Griot + bannann peze')->first();
            $jus   = $menu->items()->where('name', 'Jus de fruits frais')->first();
            if ($griot && $jus) {
                $total = (float) $griot->price + 2 * (float) $jus->price;
                $order = $menu->orders()->create([
                    'tenant_id' => $menu->tenant_id, 'reference' => \Modules\Tagtoa\App\Models\Menu\Order::generateReference(),
                    'subtotal' => $total, 'total' => $total, 'currency' => $menu->currency,
                    'status' => 'pending', 'payment_status' => 'unpaid', 'channel' => 'menu',
                    'customer_name' => 'Client Demo', 'customer_phone' => '+509 3000 0000',
                    'table_label' => '4', 'placed_at' => now(),
                ]);
                $order->items()->create(['item_id' => $griot->id, 'name' => $griot->name, 'price' => $griot->price, 'qty' => 1, 'line_total' => $griot->price]);
                $order->items()->create(['item_id' => $jus->id, 'name' => $jus->name, 'price' => $jus->price, 'qty' => 2, 'line_total' => 2 * (float) $jus->price]);
            }
        }

        // 5c) SITE — site web vitrine démo
        Site::firstOrCreate(
            ['alias' => 'demo-site'],
            [
                'name' => 'TAGTOA Lounge', 'tagline' => 'Restaurant • Lounge • Événements à Gonaïves',
                'about' => "Bienvenue chez TAGTOA Lounge. Cuisine créole raffinée, cocktails signature et ambiance lounge. "
                    ."Réservez, commandez et payez en ligne — simplement.",
                'theme' => 'dark', 'accent_color' => '#16A34A',
                'phone' => '+509 3000 0000', 'whatsapp' => '+509 3000 0000',
                'email' => 'bonjou@tagtoa.com', 'address' => 'Rue Egalité, Gonaïves, Haïti',
                'services' => [
                    ['icon' => 'fa-solid fa-utensils', 'title' => 'Restaurant', 'desc' => 'Cuisine créole et internationale, produits frais.'],
                    ['icon' => 'fa-solid fa-martini-glass', 'title' => 'Lounge & Bar', 'desc' => 'Cocktails signature et ambiance musicale.'],
                    ['icon' => 'fa-solid fa-calendar-check', 'title' => 'Événements', 'desc' => 'Soirées, anniversaires et privatisation.'],
                ],
                'hours' => [
                    ['day' => 'Lun - Jeu', 'value' => '11h - 23h'],
                    ['day' => 'Ven - Sam', 'value' => '11h - 02h'],
                    ['day' => 'Dimanche', 'value' => '12h - 22h'],
                ],
                'socials' => [
                    ['platform' => 'instagram', 'url' => 'https://instagram.com/tagtoa'],
                    ['platform' => 'whatsapp', 'url' => 'https://wa.me/50930000000'],
                ],
                'menu_id' => $menu->id ?? null,
                'pay_page_id' => $pay->id ?? null,
                'link_page_id' => $links->id ?? null,
                'is_published' => true,
            ]
        );

        // 5d) BOOKING — page de réservation démo (salon) + prestations + 1 RDV
        $booking = BookingPage::firstOrCreate(
            ['alias' => 'demo-booking'],
            [
                'name' => 'TAGTOA Studio Demo', 'tagline' => 'Coiffure • Soins • Sur rendez-vous',
                'about' => 'Réservez votre rendez-vous en ligne en quelques secondes.',
                'theme' => 'light', 'accent_color' => '#16A34A',
                'phone' => '+509 3000 0000', 'whatsapp' => '+509 3000 0000',
                'email' => 'studio@tagtoa.com', 'address' => 'Rue Egalité, Gonaïves, Haïti',
                'currency' => 'HTG', 'pay_page_id' => $pay->id ?? null, 'is_active' => true,
            ]
        );
        $bookingServices = [
            ['Coupe homme', 30, 500, 'Coupe + finitions'],
            ['Coupe + barbe', 45, 750, 'Coupe complète et taille de barbe'],
            ['Coloration', 90, 2000, 'Coloration professionnelle'],
        ];
        foreach ($bookingServices as $si => [$name, $dur, $price, $desc]) {
            $booking->services()->firstOrCreate(
                ['name' => $name],
                ['duration_min' => $dur, 'price' => $price, 'description' => $desc, 'is_active' => true, 'sort' => $si]
            );
        }
        if ($booking->bookings()->count() === 0) {
            $svc = $booking->services()->where('name', 'Coupe + barbe')->first();
            $booking->bookings()->create([
                'tenant_id' => $booking->tenant_id, 'service_id' => $svc?->id,
                'reference' => Booking::generateReference(),
                'customer_name' => 'Client Demo', 'customer_phone' => '+509 3000 0000',
                'starts_at' => now()->addDays(2)->setTime(14, 0),
                'status' => 'pending', 'price' => $svc?->price ?? 0, 'currency' => $booking->currency,
            ]);
        }

        // 6) POS — caisse démo + produits
        $terminal = Terminal::firstOrCreate(['name' => 'Caisse Demo'], ['currency' => 'HTG', 'is_active' => true]);
        foreach ([
            ['Café', 100, '☕', '#16A34A'],
            ['Sandwich', 250, '🥪', '#1D9E75'],
            ['Jus', 150, '🧃', '#E08A1E'],
        ] as $i => [$name, $price, $emoji, $color]) {
            Product::firstOrCreate(
                ['terminal_id' => $terminal->id, 'name' => $name],
                ['price' => $price, 'emoji' => $emoji, 'color' => $color, 'is_active' => true, 'sort' => $i]
            );
        }
    }
}
