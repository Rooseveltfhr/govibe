<?php

namespace App\Exports;

use App\Models\Inscription;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InscriptionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private ?int $formationId = null) {}

    public function collection()
    {
        $query = Inscription::with('formation');

        if ($this->formationId) {
            $query->where('formation_id', $this->formationId);
        }

        return $query->latest()->get();
    }

    public function headings(): array
    {
        return [
            'N° Inscription',
            'Nom Complet',
            'Sexe',
            'Date de Naissance',
            'Téléphone',
            'Email',
            'Département',
            'Ville',
            'Profession',
            'Niveau d\'Étude',
            'Formation',
            'Source Information',
            'Objectif',
            'Attentes',
            'Date d\'Inscription',
        ];
    }

    public function map($inscription): array
    {
        return [
            $inscription->numero_inscription,
            $inscription->nom_complet,
            $inscription->sexe,
            $inscription->date_naissance?->format('d/m/Y'),
            $inscription->telephone,
            $inscription->email,
            $inscription->departement,
            $inscription->ville,
            $inscription->profession,
            $inscription->niveau_etude,
            $inscription->formation?->nom,
            $inscription->source_info,
            $inscription->objectif,
            $inscription->attentes,
            $inscription->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1e3a5f']]],
        ];
    }
}
