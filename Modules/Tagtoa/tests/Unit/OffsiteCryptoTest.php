<?php

namespace Modules\Tagtoa\Tests\Unit;

/*
| BK-1b — chiffrement des copies hors site. Sans clé privée, un fichier est illisible ; modifié ou tronqué, il est refusé.
*/

use Modules\Tagtoa\App\Services\Backup\OffsiteCrypto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OffsiteCryptoTest extends TestCase
{
    private string $dir;
    private array $kp;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/tagtoa-crypto-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
        $this->kp = OffsiteCrypto::generateKeypair();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
    }

    private function roundTrip(string $plain, ?array $kp = null): array
    {
        $kp ??= $this->kp;
        $src = $this->dir.'/src.bin';
        $enc = $this->dir.'/src.enc';
        $dec = $this->dir.'/src.dec';
        @unlink($enc); @unlink($dec);
        file_put_contents($src, $plain);
        OffsiteCrypto::encryptFile($src, $enc, OffsiteCrypto::publicKeyFromBase64($kp['public']));

        return [$src, $enc, $dec];
    }

    #[DataProvider('sizes')]
    public function test_round_trip_restores_the_exact_bytes_at_every_size_boundary(int $n): void
    {
        $plain = $n === 0 ? '' : random_bytes($n);
        [, $enc, $dec] = $this->roundTrip($plain);

        $this->assertSame(OffsiteCrypto::encryptedSize($n), filesize($enc), 'La taille chiffrée prévue sert à reconnaître un fichier déjà envoyé.');
        OffsiteCrypto::decryptFile($enc, $dec, OffsiteCrypto::privateKeyFromBase64($this->kp['private']));
        $this->assertSame($plain, file_get_contents($dec));
    }

    public static function sizes(): array
    {
        $c = OffsiteCrypto::CHUNK;

        return [[0], [1], [$c - 1], [$c], [$c + 1], [2 * $c], [3 * $c + 17]];
    }

    public function test_the_ciphertext_does_not_contain_the_plaintext_and_differs_each_time(): void
    {
        $plain = str_repeat('SECRET-PASSWORD-1234 ', 500);
        [$src, $enc] = $this->roundTrip($plain);
        $this->assertStringNotContainsString('SECRET-PASSWORD', (string) file_get_contents($enc));

        $enc2 = $this->dir.'/second.enc';
        OffsiteCrypto::encryptFile($src, $enc2, OffsiteCrypto::publicKeyFromBase64($this->kp['public']));
        $this->assertNotSame(file_get_contents($enc), file_get_contents($enc2), 'Clé et nonce à usage unique : deux chiffrements ne se ressemblent pas.');
    }

    public function test_the_wrong_private_key_is_refused_and_leaves_no_output(): void
    {
        [, $enc, $dec] = $this->roundTrip('données');
        $autre = OffsiteCrypto::generateKeypair();

        try {
            OffsiteCrypto::decryptFile($enc, $dec, OffsiteCrypto::privateKeyFromBase64($autre['private']));
            $this->fail('Une mauvaise clé ne doit pas déchiffrer.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Clé privée incorrecte', $e->getMessage());
        }
        $this->assertFileDoesNotExist($dec);
        $this->assertFileDoesNotExist($dec.'.partial');
    }

    public function test_a_flipped_byte_anywhere_in_the_stream_is_detected(): void
    {
        [, $enc, $dec] = $this->roundTrip(random_bytes(OffsiteCrypto::CHUNK * 2 + 100));
        $bin = file_get_contents($enc);
        $priv = OffsiteCrypto::privateKeyFromBase64($this->kp['private']);

        foreach ([200, strlen($bin) / 2, strlen($bin) - 5] as $pos) {
            $m = $bin;
            $m[(int) $pos] = chr(ord($m[(int) $pos]) ^ 0x01);
            file_put_contents($enc, $m);
            try {
                OffsiteCrypto::decryptFile($enc, $dec, $priv);
                $this->fail("Octet modifié en $pos non détecté.");
            } catch (\RuntimeException $e) {
                $this->assertFileDoesNotExist($dec, 'Rien d\'à moitié déchiffré ne reste sur le disque.');
                if ($pos > 200) {   // dans un bloc : l'authentification du bloc le refuse, pas une simple fin de flux manquante
                    $this->assertStringContainsString('modifié ou corrompu', $e->getMessage());
                }
            }
        }
    }

    public function test_truncation_at_a_block_boundary_is_detected(): void
    {
        [, $enc, $dec] = $this->roundTrip(random_bytes(OffsiteCrypto::CHUNK * 3));
        $bin = file_get_contents($enc);
        $entete = strlen(OffsiteCrypto::MAGIC) + 80 + 24;
        $bloc = OffsiteCrypto::CHUNK + 17;
        file_put_contents($enc, substr($bin, 0, $entete + 2 * $bloc));   // coupé proprement après le 2e bloc : il manque le bloc FINAL

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tronqué');
        OffsiteCrypto::decryptFile($enc, $dec, OffsiteCrypto::privateKeyFromBase64($this->kp['private']));
    }

    public function test_extra_data_after_the_end_is_refused_and_a_foreign_file_is_not_accepted(): void
    {
        // Un dernier bloc PLEIN : l'excédent n'est alors pas avalé par la lecture du bloc et seul le contrôle de fin de flux le voit.
        [, $enc, $dec] = $this->roundTrip(random_bytes(OffsiteCrypto::CHUNK));
        $priv = OffsiteCrypto::privateKeyFromBase64($this->kp['private']);

        file_put_contents($enc, file_get_contents($enc).'x');
        try {
            OffsiteCrypto::decryptFile($enc, $dec, $priv);
            $this->fail('Données en trop acceptées.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('en trop', $e->getMessage());
            $this->assertFileDoesNotExist($dec);
        }

        file_put_contents($enc, 'pas une sauvegarde');
        $this->expectExceptionMessage('pas une sauvegarde TAGTOA');
        OffsiteCrypto::decryptFile($enc, $dec, $priv);
    }

    public function test_invalid_keys_are_rejected(): void
    {
        foreach (['', 'pas-du-base64!!', base64_encode('trop court')] as $bad) {
            try {
                OffsiteCrypto::publicKeyFromBase64($bad);
                $this->fail('Clé publique invalide acceptée : '.$bad);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
            try {
                OffsiteCrypto::privateKeyFromBase64($bad);
                $this->fail('Clé privée invalide acceptée : '.$bad);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_encrypt_side_needs_only_the_public_key(): void
    {
        $src = $this->dir.'/a.bin';
        file_put_contents($src, 'x');
        OffsiteCrypto::encryptFile($src, $this->dir.'/a.enc', OffsiteCrypto::publicKeyFromBase64($this->kp['public']));
        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->dir.'/a.enc')), -4));
        $this->assertStringNotContainsString($this->kp['private'], (string) file_get_contents($this->dir.'/a.enc'));
    }
}
