<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inscription extends Model
{
    protected $fillable = [
        'numero_inscription', 'nom_complet', 'sexe', 'date_naissance',
        'telephone', 'email', 'departement', 'ville', 'profession',
        'niveau_etude', 'formation_id', 'source_info', 'objectif',
        'attentes', 'qr_code', 'present', 'scanned_at',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'present' => 'boolean',
        'scanned_at' => 'datetime',
    ];

    public function formation()
    {
        return $this->belongsTo(Formation::class);
    }

    public static function generateNumero(): string
    {
        $year = date('Y');
        $count = self::whereYear('created_at', $year)->count() + 1;
        return 'GVB-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
