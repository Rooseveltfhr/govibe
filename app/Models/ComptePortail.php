<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class ComptePortail extends Authenticatable
{
    use HasUuids;

    protected $table = 'comptes_portail';

    protected $fillable = [
        'uuid', 'client_id', 'nom', 'email', 'telephone', 'password',
        'email_verifie_le', 'jeton_verification', 'actif',
        'dernier_login_le', 'dernier_login_ip',
    ];

    protected $hidden = ['password', 'remember_token', 'jeton_verification'];

    protected $casts = [
        'email_verifie_le' => 'datetime',
        'dernier_login_le' => 'datetime',
        'actif' => 'boolean',
        'password' => 'hashed',
    ];

    /** HasUuids remplirait aussi la clé primaire : ici seul uuid est concerné. */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function connexions(): HasMany
    {
        return $this->hasMany(ConnexionPortail::class, 'compte_portail_id');
    }

    public function estVerifie(): bool
    {
        return $this->email_verifie_le !== null;
    }

    /**
     * L'accès aux données du client est refusé tant que l'adresse n'est pas
     * vérifiée : sans cela, s'inscrire avec l'email d'un tiers suffirait à
     * lire ses factures.
     */
    public function peutVoirSesDonnees(): bool
    {
        return $this->actif
            && (! config('govibe.portail.verification_email_obligatoire') || $this->estVerifie());
    }

    public static function genererJeton(): string
    {
        return Str::random(64);
    }
}
