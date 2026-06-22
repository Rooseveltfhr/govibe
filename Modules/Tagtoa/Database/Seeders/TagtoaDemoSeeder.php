<?php

namespace Modules\Tagtoa\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Links\Link;
use Modules\Tagtoa\App\Models\Links\LinkPage;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
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
        PaymentMethod::firstOrCreate(
            ['payment_page_id' => $pay->id, 'type' => 'moncash'],
            ['label' => 'MonCash', 'account_holder' => 'TAGTOA Demo', 'account_number' => '+509 0000 0000', 'requires_proof' => true, 'is_active' => true, 'sort' => 1]
        );
        PaymentMethod::firstOrCreate(
            ['payment_page_id' => $pay->id, 'type' => 'natcash'],
            ['label' => 'NatCash', 'account_holder' => 'TAGTOA Demo', 'account_number' => '+509 1111 1111', 'requires_proof' => true, 'is_active' => true, 'sort' => 2]
        );

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

        // 5) POS — caisse démo + produits
        $terminal = Terminal::firstOrCreate(['name' => 'Caisse Demo'], ['currency' => 'HTG', 'is_active' => true]);
        foreach ([
            ['Café', 100, '☕', '#0055FF'],
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
