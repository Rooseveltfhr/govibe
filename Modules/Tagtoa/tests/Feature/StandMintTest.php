<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandBatch;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Support\Stand\StandId;
use Modules\Tagtoa\App\Support\Stand\StandState;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Fabriquer un lot de Smart Stands.
 *
 * Ce que ces tests protègent part chez un imprimeur et devient dix mille
 * objets physiques. Une erreur ici ne se corrige pas par un correctif.
 */
class StandMintTest extends TestCase
{
    use RefreshDatabase;

    private function minter(): StandMinter
    {
        return app(StandMinter::class);
    }

    /* ------------------------------------------------------------------
       Ce qui sort de l'usine.
       ------------------------------------------------------------------ */

    public function test_a_batch_produces_the_expected_range(): void
    {
        $r = $this->minter()->mint('TAGTOA-2026-001', 50);

        $this->assertSame(50, Stand::count());
        $this->assertSame('TG-000001', Stand::orderBy('serial')->first()->public_id);
        $this->assertSame('TG-000050', Stand::orderByDesc('serial')->first()->public_id);
        $this->assertSame(50, $r['batch']->quantity);
    }

    public function test_a_batch_can_start_where_the_previous_one_ended(): void
    {
        $this->minter()->mint('TAGTOA-2026-001', 10);
        $this->minter()->mint('TAGTOA-2026-002', 10, 'TG', 11);

        $this->assertSame('TG-000011', Stand::where('serial', 11)->first()->public_id);
        $this->assertSame(20, Stand::count());
        $this->assertSame(20, Stand::distinct('public_id')->count('public_id'),
            'Deux objets physiques pour un seul identifiant seraient irrattrapables.');
    }

    public function test_every_stand_starts_unclaimed_and_held_by_the_platform(): void
    {
        $this->minter()->mint('TAGTOA-2026-001', 5);

        foreach (Stand::all() as $stand) {
            $this->assertSame(StandState::MANUFACTURED, $stand->physical_state);
            $this->assertSame(StandState::UNCLAIMED, $stand->digital_state);
            $this->assertSame('platform', $stand->holder_type);
            $this->assertNull($stand->tenant_id, 'Un stand neuf n\'appartient à aucun commerce.');
            $this->assertTrue($stand->isClaimable());
        }
    }

    /* ------------------------------------------------------------------
       LA règle : le clair ne touche jamais la base.
       ------------------------------------------------------------------ */

    public function test_the_plain_code_never_reaches_the_database(): void
    {
        // Une fuite de la base ne doit donner aucun stand à personne. Même
        // règle que le PIN des employés et l'UID des cartes.
        $r = $this->minter()->mint('TAGTOA-2026-001', 20);

        foreach ($r['secrets'] as $publicId => $clair) {
            $brut = \Illuminate\Support\Facades\DB::table('tagtoa_stands')
                ->where('public_id', $publicId)->first();

            $this->assertNotSame($clair, $brut->secret_hash);
            $this->assertStringNotContainsString($clair, (string) $brut->secret_hash);
            $this->assertStringStartsWith('$2y$', $brut->secret_hash, 'Le secret doit être haché.');
        }
    }

    public function test_a_serialised_stand_never_carries_its_secret(): void
    {
        // Un `toJson()` distrait ne doit pas faire voyager le hachage jusqu'à
        // un écran de revendeur, qui ne doit rien en connaître.
        $this->minter()->mint('TAGTOA-2026-001', 1);

        $this->assertArrayNotHasKey('secret_hash', Stand::first()->toArray());
    }

    public function test_each_stand_gets_its_own_secret(): void
    {
        // Deux stands qui partagent un code, c'est un commerce qui peut
        // réclamer le stand d'un autre.
        $r = $this->minter()->mint('TAGTOA-2026-001', 200);

        $this->assertCount(200, array_unique($r['secrets']));
    }

    /* ------------------------------------------------------------------
       Vérifier un code.
       ------------------------------------------------------------------ */

    public function test_the_right_code_opens_its_own_stand_and_no_other(): void
    {
        $r = $this->minter()->mint('TAGTOA-2026-001', 5);

        $premier = Stand::orderBy('serial')->first();
        $second  = Stand::orderBy('serial')->skip(1)->first();

        $this->assertTrue($this->minter()->verify($premier, $r['secrets'][$premier->public_id]));
        $this->assertFalse($this->minter()->verify($second, $r['secrets'][$premier->public_id]),
            'Le code d\'un stand ne doit jamais ouvrir celui du voisin.');
    }

    public function test_the_code_is_accepted_however_it_was_retyped(): void
    {
        // Le client lit son étiquette dans un restaurant mal éclairé : « O »
        // pour zéro, des tirets ajoutés, des minuscules.
        $r = $this->minter()->mint('TAGTOA-2026-001', 1);
        $stand = Stand::first();
        $clair = $r['secrets'][$stand->public_id];

        $this->assertTrue($this->minter()->verify($stand, StandId::pretty($clair)));
        $this->assertTrue($this->minter()->verify($stand, strtolower($clair)));
        $this->assertTrue($this->minter()->verify($stand, ' '.$clair.' '));
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $this->minter()->mint('TAGTOA-2026-001', 1);

        $this->assertFalse($this->minter()->verify(Stand::first(), 'A3F9K2MP'));
        $this->assertFalse($this->minter()->verify(Stand::first(), ''));
        $this->assertFalse($this->minter()->verify(Stand::first(), null));
        $this->assertFalse($this->minter()->verify(null, 'A3F9K2MP'));
    }

    public function test_a_lost_stand_is_dead_even_with_the_right_code(): void
    {
        // On ne peut pas empêcher qu'on vole un objet non gratté. On peut faire
        // qu'il ne serve à rien — c'est la seule protection réelle.
        $r = $this->minter()->mint('TAGTOA-2026-001', 1);
        $stand = Stand::first();
        $clair = $r['secrets'][$stand->public_id];

        $this->assertTrue($this->minter()->verify($stand, $clair));

        $stand->update(['physical_state' => StandState::LOST]);

        $this->assertFalse($this->minter()->verify($stand->fresh(), $clair));
    }

    public function test_a_recalled_batch_closes_all_its_stands_at_once(): void
    {
        // Défaut d'impression, secrets compromis : dix mille lignes ne se
        // ferment pas une par une.
        $r = $this->minter()->mint('TAGTOA-2026-001', 3);
        $stand = Stand::with('batch')->first();
        $clair = $r['secrets'][$stand->public_id];

        StandBatch::first()->update(['recalled_at' => now()]);

        $this->assertFalse(Stand::with('batch')->first()->isClaimable());
        $this->assertFalse($this->minter()->verify(Stand::with('batch')->first(), $clair));
    }

    /* ------------------------------------------------------------------
       Réémission.
       ------------------------------------------------------------------ */

    public function test_reissuing_kills_the_old_code(): void
    {
        // Panneau gratté en transit : l'ancien code doit cesser de valoir.
        $r = $this->minter()->mint('TAGTOA-2026-001', 1);
        $stand = Stand::first();
        $ancien = $r['secrets'][$stand->public_id];

        $nouveau = $this->minter()->reissue($stand, 'Panneau gratté en transit');

        $frais = Stand::with('batch')->first();

        $this->assertFalse($this->minter()->verify($frais, $ancien), 'L\'ancien code doit mourir.');
        $this->assertTrue($this->minter()->verify($frais, $nouveau));
        $this->assertSame(2, $frais->secret_version, 'La réémission doit se voir dans l\'historique.');
    }

    /* ------------------------------------------------------------------
       Ce que la commande produit pour l'imprimeur.
       ------------------------------------------------------------------ */

    public function test_the_command_writes_what_the_printer_needs(): void
    {
        $dossier = sys_get_temp_dir().'/tagtoa-batch-'.bin2hex(random_bytes(4));

        $this->artisan('tagtoa:stands:mint', [
            '--batch' => 'TAGTOA-2026-001', '--count' => 5, '--out' => $dossier,
        ])->assertExitCode(0);

        foreach (['stands.csv', 'nfc.csv', 'secrets.csv', 'manifest.json'] as $f) {
            $this->assertFileExists($dossier.'/'.$f);
        }

        // Le fichier public ne contient AUCUN secret.
        $public = file_get_contents($dossier.'/stands.csv');
        $secrets = file_get_contents($dossier.'/secrets.csv');

        foreach (explode("\n", trim($secrets)) as $i => $ligne) {
            if ($i === 0 || $ligne === '') {
                continue;
            }
            [$id, $code] = explode(',', $ligne);
            $this->assertStringNotContainsString($code, $public,
                "Le code de $id se retrouve dans le fichier d'impression public.");
        }

        array_map('unlink', glob($dossier.'/*'));
        rmdir($dossier);
    }

    public function test_the_secret_file_is_not_readable_by_anyone_else(): void
    {
        // Ce fichier compromet un lot entier — dix mille commerces.
        $dossier = sys_get_temp_dir().'/tagtoa-batch-'.bin2hex(random_bytes(4));

        $this->artisan('tagtoa:stands:mint', [
            '--batch' => 'TAGTOA-2026-002', '--count' => 2, '--out' => $dossier,
        ]);

        $this->assertSame('0600', substr(sprintf('%o', fileperms($dossier.'/secrets.csv')), -4));
        $this->assertSame('0644', substr(sprintf('%o', fileperms($dossier.'/stands.csv')), -4));

        array_map('unlink', glob($dossier.'/*'));
        rmdir($dossier);
    }

    public function test_the_nfc_file_carries_the_same_url_as_the_qr(): void
    {
        // Taper et scanner doivent mener au même endroit : c'est le même objet.
        $dossier = sys_get_temp_dir().'/tagtoa-batch-'.bin2hex(random_bytes(4));

        $this->artisan('tagtoa:stands:mint', [
            '--batch' => 'TAGTOA-2026-003', '--count' => 3, '--out' => $dossier,
        ]);

        $qr  = file($dossier.'/stands.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $nfc = file($dossier.'/nfc.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        for ($i = 1; $i < count($qr); $i++) {
            [$idQr, $urlQr] = explode(',', $qr[$i]);
            [$idNfc, $urlNfc] = explode(',', $nfc[$i]);

            $this->assertSame($idQr, $idNfc);
            $this->assertSame($urlQr, $urlNfc);
        }

        // La puce doit être verrouillée : sans cela n'importe quel téléphone
        // Android la réécrit en trois secondes vers un site de phishing.
        $this->assertStringContainsString('readonly', $nfc[1]);

        array_map('unlink', glob($dossier.'/*'));
        rmdir($dossier);
    }

    public function test_a_batch_is_never_minted_twice(): void
    {
        // Refabriquer un lot produirait des identifiants en double — deux
        // objets physiques pour une seule ligne.
        $this->minter()->mint('TAGTOA-2026-001', 3);

        $this->artisan('tagtoa:stands:mint', ['--batch' => 'TAGTOA-2026-001', '--count' => 3])
            ->assertExitCode(1);

        $this->assertSame(3, Stand::count());
    }

    public function test_a_batch_without_a_code_is_refused(): void
    {
        $this->artisan('tagtoa:stands:mint', ['--count' => 3])->assertExitCode(1);

        $this->assertSame(0, Stand::count());
    }
}
