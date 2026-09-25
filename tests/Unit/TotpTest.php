<?php

namespace Tests\Unit;

use App\Securite\Totp;
use PHPUnit\Framework\TestCase;

/**
 * Les vecteurs de test publiés par la RFC 6238 (annexe B).
 *
 * C'est eux qui répondent de cette implémentation : si un seul tombe, les
 * codes produits ici ne seraient pas ceux qu'affiche Google Authenticator, et
 * personne ne pourrait se connecter.
 */
class TotpTest extends TestCase
{
    /** Le secret de la RFC : « 12345678901234567890 » en ASCII. */
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_les_vecteurs_de_la_rfc_6238_en_huit_chiffres(): void
    {
        $vecteurs = [
            59 => '94287082',
            1111111109 => '07081804',
            1111111111 => '14050471',
            1234567890 => '89005924',
            2000000000 => '69279037',
            20000000000 => '65353130',
        ];

        foreach ($vecteurs as $instant => $attendu) {
            $this->assertSame(
                $attendu,
                Totp::code(self::SECRET, Totp::pasDeTemps($instant), 8),
                "Vecteur RFC 6238 à T = {$instant}"
            );
        }
    }

    public function test_les_memes_vecteurs_en_six_chiffres(): void
    {
        // Six chiffres, c'est la longueur que GOVIBE utilise : ce sont les six
        // derniers de la valeur à huit.
        $vecteurs = [
            59 => '287082',
            1111111109 => '081804',
            1111111111 => '050471',
            1234567890 => '005924',
            2000000000 => '279037',
        ];

        foreach ($vecteurs as $instant => $attendu) {
            $this->assertSame($attendu, Totp::code(self::SECRET, Totp::pasDeTemps($instant)));
        }
    }

    public function test_le_secret_fait_bien_cent_soixante_bits(): void
    {
        $secret = Totp::secret();

        // 20 octets encodés en base32 donnent 32 caractères.
        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertSame(20, strlen(Totp::decoderBase32($secret)));

        // Deux appels ne rendent jamais le même : un secret prévisible se
        // recalcule, et toute la double authentification tombe avec lui.
        $this->assertNotSame($secret, Totp::secret());
    }

    public function test_base32_fait_l_aller_retour(): void
    {
        foreach (['', 'a', 'ab', 'abc', 'abcd', 'abcde', '12345678901234567890'] as $clair) {
            $this->assertSame($clair, Totp::decoderBase32(Totp::encoderBase32($clair)));
        }
    }

    public function test_un_secret_recopie_avec_ses_espaces_reste_valide(): void
    {
        // C'est exactement ce que fait quelqu'un qui tape la clé à la main
        // depuis l'écran, groupe par groupe.
        $this->assertSame(
            Totp::code(self::SECRET, 1),
            Totp::code(Totp::lisible(self::SECRET), 1)
        );
    }

    // ── Vérification ─────────────────────────────────────

    public function test_le_code_courant_est_accepte_et_rend_son_pas_de_temps(): void
    {
        $instant = 1234567890;
        $pas = Totp::pasDeTemps($instant);
        $code = Totp::code(self::SECRET, $pas);

        $this->assertSame($pas, Totp::verifier(self::SECRET, $code, 1, $instant));
    }

    public function test_la_fenetre_tolere_une_periode_de_derive_pas_davantage(): void
    {
        $instant = 1234567890;
        $pas = Totp::pasDeTemps($instant);

        // Les horloges de téléphone dérivent : ±30 secondes passent.
        $this->assertNotNull(Totp::verifier(self::SECRET, Totp::code(self::SECRET, $pas - 1), 1, $instant));
        $this->assertNotNull(Totp::verifier(self::SECRET, Totp::code(self::SECRET, $pas + 1), 1, $instant));

        // Au-delà, non : élargir la fenêtre allonge d'autant la durée de vie
        // d'un code volé.
        $this->assertNull(Totp::verifier(self::SECRET, Totp::code(self::SECRET, $pas - 2), 1, $instant));
        $this->assertNull(Totp::verifier(self::SECRET, Totp::code(self::SECRET, $pas + 2), 1, $instant));
    }

    public function test_un_code_faux_ou_mal_forme_est_refuse(): void
    {
        $instant = 1234567890;

        foreach (['', '000000', '12345', '1234567', 'abcdef', '  ', '00000a'] as $code) {
            $this->assertNull(
                Totp::verifier(self::SECRET, $code, 1, $instant),
                "Le code « {$code} » n'aurait pas dû être accepté."
            );
        }
    }

    public function test_un_code_saisi_avec_un_espace_passe_quand_meme(): void
    {
        $instant = 1234567890;
        $pas = Totp::pasDeTemps($instant);
        $code = Totp::code(self::SECRET, $pas);

        // Les lecteurs affichent « 123 456 » : refuser l'espace ferait échouer
        // une saisie pourtant juste.
        $espace = substr($code, 0, 3).' '.substr($code, 3);

        $this->assertSame($pas, Totp::verifier(self::SECRET, $espace, 1, $instant));
    }

    public function test_un_code_d_un_autre_secret_ne_passe_pas(): void
    {
        $instant = 1234567890;
        $autre = Totp::secret();

        $this->assertNull(
            Totp::verifier(self::SECRET, Totp::code($autre, Totp::pasDeTemps($instant)), 1, $instant)
        );
    }

    // ── Adresse otpauth ──────────────────────────────────

    public function test_l_adresse_otpauth_porte_tout_ce_que_le_lecteur_attend(): void
    {
        $uri = Totp::uri(self::SECRET, 'admin@govibeht.com', 'GOVIBE');

        $this->assertStringStartsWith('otpauth://totp/GOVIBE:admin%40govibeht.com?', $uri);
        $this->assertStringContainsString('secret='.self::SECRET, $uri);
        $this->assertStringContainsString('issuer=GOVIBE', $uri);
        $this->assertStringContainsString('algorithm=SHA1', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }
}
