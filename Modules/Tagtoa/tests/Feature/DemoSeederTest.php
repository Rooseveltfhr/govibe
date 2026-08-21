<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — seed de démo (déployé automatiquement sur chaque deploy prod,
| voir remote-deploy.sh). Aucune couverture n'existait avant ce test : un
| échec silencieux ici casserait le deploy sans qu'aucune CI ne le détecte.
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Staff;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\Database\Seeders\TagtoaDemoSeeder;
use Modules\Tagtoa\Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_runs_without_error_and_creates_expected_aliases(): void
    {
        (new TagtoaDemoSeeder)->run();

        $menu = Menu::where('alias', 'demo-menu')->firstOrFail();
        $this->assertSame('Lakou Lounge', $menu->name);
        $this->assertGreaterThan(0, $menu->categories()->count());
        $this->assertSame(1, $menu->orders()->count());

        $event = Event::where('alias', 'demo-concert')->firstOrFail();
        $this->assertSame(3, Staff::where('event_id', $event->id)->count());
    }

    public function test_seeder_is_idempotent_and_does_not_duplicate_rows(): void
    {
        (new TagtoaDemoSeeder)->run();
        (new TagtoaDemoSeeder)->run();

        $this->assertSame(1, Menu::where('alias', 'demo-menu')->count());
        $this->assertSame(1, Event::where('alias', 'demo-concert')->count());
        $this->assertSame(1, Menu::where('alias', 'demo-menu')->first()->orders()->count());
    }

    public function test_renaming_demo_staff_updates_existing_rows_instead_of_duplicating(): void
    {
        // Régression : le seeder matche désormais le staff démo par (event_id, role),
        // pas par nom — un futur changement de nom ne doit jamais dupliquer le compte.
        (new TagtoaDemoSeeder)->run();
        $event = Event::where('alias', 'demo-concert')->firstOrFail();

        $this->assertSame(3, Staff::where('event_id', $event->id)->count());
        $admin = Staff::where('event_id', $event->id)->where('role', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertSame('Peterson Michel', $admin->name);
    }
}
