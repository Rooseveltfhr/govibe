<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — le script de la page publique doit rester du JavaScript valide
|--------------------------------------------------------------------------
| Signalé : le bouton « + » ne faisait rien sur le menu public. Cause : les
| messages de l'Agent IA étaient écrits `{{ json_encode(__('…')) }}` dans le
| <script>. `{{ }}` échappe le HTML, donc les guillemets du JSON devenaient
| `&quot;` — « aiBulle(&quot;Dites-moi…&quot;) » n'est pas du JavaScript. Une
| seule erreur de syntaxe invalide TOUT le bloc : add(), le panier, l'envoi de
| la commande, rien n'existait plus, et la page restait belle et muette.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\Tests\TestCase;

class MenuPublicScriptTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $menu = Menu::create([
            'tenant_id' => 't-1', 'name' => 'Chez Rose', 'alias' => 'chez-rose',
            'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true,
        ]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => "Griot d'Haïti",
            'price' => 350, 'is_available' => true]);

        return $this->get('/menu/chez-rose')->assertOk()->getContent();
    }

    /**
     * Un « &quot; » qui OUVRE une chaîne (après ( , + = ) est toujours une erreur
     * d'échappement. (Le « &quot; » littéral de esc() suit un « : » : légitime.)
     */
    public function test_no_inline_script_opens_a_string_with_an_html_escaped_quote(): void
    {
        preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#s', $this->page(), $m);

        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $i => $js) {
            $this->assertSame(0, preg_match('/[(,+=]\s*(&quot;|&#039;)/', $js), "Le script inline n°$i ouvre une chaîne par un guillemet échappé en HTML.");
        }
    }

    /** Le vrai garde : un parseur JavaScript. Ignoré si `node` n'est pas installé. */
    public function test_every_inline_script_parses_as_javascript(): void
    {
        if (trim((string) shell_exec('command -v node')) === '') {
            $this->markTestSkipped('node indisponible');
        }
        preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#s', $this->page(), $m);

        foreach ($m[1] as $i => $js) {
            $f = tempnam(sys_get_temp_dir(), 'menujs').'.js';
            file_put_contents($f, $js);
            exec('node --check '.escapeshellarg($f).' 2>&1', $out, $code);
            @unlink($f);
            $this->assertSame(0, $code, "Le script inline n°$i ne se parse pas :\n".implode("\n", $out));
            $out = [];
        }
    }
}
