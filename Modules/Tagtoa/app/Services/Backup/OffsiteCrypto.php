<?php

namespace Modules\Tagtoa\App\Services\Backup;

/**
 * Chiffrement des sauvegardes avant qu'elles quittent le serveur (BK-1b).
 *
 * Asymétrique : le serveur ne détient que la clé PUBLIQUE. Chaque fichier est chiffré avec une clé aléatoire à usage unique
 * (libsodium secretstream XChaCha20-Poly1305, par blocs : mémoire constante, troncature et modification détectées), et cette clé
 * est scellée avec la clé publique (crypto_box_seal). Seule la clé PRIVÉE — gardée hors du serveur — peut ouvrir un fichier :
 * quiconque prend le serveur, ses variables d'environnement ou le bucket ne peut rien lire.
 *
 * Format :  "TGBK1\n" (6) | clé scellée (80) | en-tête secretstream (24) | blocs de (CHUNK + 17) octets, le dernier marqué FINAL.
 */
class OffsiteCrypto
{
    public const MAGIC = "TGBK1\n";
    public const CHUNK = 65536;
    private const SEALED = 80;   // 32 (clé) + SODIUM_CRYPTO_BOX_SEALBYTES (48)
    private const HEADER = 24;
    private const ABYTES = 17;

    /** @return array{public:string,private:string} base64 */
    public static function generateKeypair(): array
    {
        $kp = sodium_crypto_box_keypair();

        return [
            'public'  => base64_encode(sodium_crypto_box_publickey($kp)),
            'private' => base64_encode(sodium_crypto_box_secretkey($kp)),
        ];
    }

    /** Taille exacte du fichier chiffré pour $plainSize octets en clair (sert à reconnaître un fichier déjà envoyé). */
    public static function encryptedSize(int $plainSize): int
    {
        $blocs = max(1, (int) ceil($plainSize / self::CHUNK));

        return strlen(self::MAGIC) + self::SEALED + self::HEADER + $plainSize + $blocs * self::ABYTES;
    }

    public static function publicKeyFromBase64(string $b64): string
    {
        $raw = base64_decode(trim($b64), true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new \InvalidArgumentException('Clé publique invalide (attendu : 32 octets en base64).');
        }

        return $raw;
    }

    public static function privateKeyFromBase64(string $b64): string
    {
        $raw = base64_decode(trim($b64), true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) {
            throw new \InvalidArgumentException('Clé privée invalide (attendu : 32 octets en base64).');
        }

        return $raw;
    }

    public static function encryptFile(string $src, string $dest, string $publicKey): void
    {
        $in = @fopen($src, 'rb');
        if ($in === false) {
            throw new \RuntimeException('Fichier à chiffrer illisible.');
        }
        $old = umask(0077);   // jamais lisible par d'autres, pas même un instant
        $out = @fopen($dest, 'wb');
        umask($old);
        if ($out === false) {
            fclose($in);
            throw new \RuntimeException('Fichier chiffré non inscriptible.');
        }
        @chmod($dest, 0600);

        try {
            $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            fwrite($out, self::MAGIC.sodium_crypto_box_seal($key, $publicKey).$header);
            sodium_memzero($key);

            $buf = self::readFull($in, self::CHUNK);
            do {
                $next = self::readFull($in, self::CHUNK);
                $final = $next === '';   // le bloc courant est le dernier
                fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state, $buf, '',
                    $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE
                ));
                $buf = $next;
            } while (! $final);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /** Déchiffre. Lève une exception si la clé est mauvaise, si le fichier a été modifié OU tronqué. */
    public static function decryptFile(string $src, string $dest, string $privateKey): void
    {
        $in = @fopen($src, 'rb');
        if ($in === false) {
            throw new \RuntimeException('Fichier chiffré illisible.');
        }
        $tmp = $dest.'.partial';
        $old = umask(0077);
        $out = @fopen($tmp, 'wb');
        umask($old);
        if ($out === false) {
            fclose($in);
            throw new \RuntimeException('Fichier de sortie non inscriptible.');
        }
        @chmod($tmp, 0600);

        $ok = false;
        try {
            if (self::readFull($in, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new \RuntimeException('Ce fichier n\'est pas une sauvegarde TAGTOA chiffrée.');
            }
            $sealed = self::readFull($in, self::SEALED);
            $header = self::readFull($in, self::HEADER);
            if (strlen($sealed) !== self::SEALED || strlen($header) !== self::HEADER) {
                throw new \RuntimeException('Fichier tronqué (en-tête incomplet).');
            }
            $key = sodium_crypto_box_seal_open($sealed, sodium_crypto_box_keypair_from_secretkey_and_publickey($privateKey, sodium_crypto_box_publickey_from_secretkey($privateKey)));
            if ($key === false) {
                throw new \RuntimeException('Clé privée incorrecte pour ce fichier.');
            }
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            sodium_memzero($key);

            $final = false;
            while (true) {
                $bloc = self::readFull($in, self::CHUNK + self::ABYTES);
                if ($bloc === '') {
                    break;
                }
                $res = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $bloc);
                if ($res === false) {
                    throw new \RuntimeException('Fichier modifié ou corrompu (bloc invalide).');
                }
                [$plain, $tag] = $res;
                fwrite($out, $plain);
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $final = true;
                    break;
                }
            }
            if (! $final) {
                throw new \RuntimeException('Fichier tronqué (fin de flux absente).');
            }
            if (self::readFull($in, 1) !== '') {
                throw new \RuntimeException('Données en trop après la fin du flux.');
            }
            $ok = true;
        } finally {
            fclose($in);
            fclose($out);
            if ($ok) {
                rename($tmp, $dest);
            } else {
                @unlink($tmp);
            }
        }
    }

    /** fread jusqu'à $n octets (ou la fin du fichier). */
    private static function readFull($h, int $n): string
    {
        $s = '';
        while (strlen($s) < $n && ! feof($h)) {
            $r = fread($h, $n - strlen($s));
            if ($r === false || $r === '') {
                break;
            }
            $s .= $r;
        }

        return $s;
    }
}
