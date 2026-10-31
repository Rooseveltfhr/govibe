<?php

namespace Modules\Tagtoa\App\Support\Net;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Règle de validation : l'URL de callback doit pouvoir être appelée sans danger (voir SafeUrl). */
class SafeCallbackUrl implements ValidationRule
{
    private const MESSAGES = [
        'scheme'          => 'L\'URL doit commencer par https://.',
        'credentials'     => 'L\'URL ne doit pas contenir d\'identifiants (utilisateur:motdepasse@).',
        'private_host'    => 'L\'URL ne peut pas viser une adresse interne ou locale.',
        'private_address' => 'L\'URL ne peut pas viser une adresse privée ou réservée.',
        'unresolvable'    => 'Le nom de domaine de l\'URL est introuvable.',
        'port'            => 'Le port de l\'URL est invalide.',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $r = SafeUrl::check((string) $value);
        if (! $r['ok']) {
            $fail(__(self::MESSAGES[$r['reason']] ?? 'URL de callback invalide.'));
        }
    }
}
