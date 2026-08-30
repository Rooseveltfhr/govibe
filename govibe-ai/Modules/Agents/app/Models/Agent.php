<?php

namespace Modules\Agents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Agents\DTO\AgentDefinition;
use Modules\Agents\Templates\AgentTemplateRegistry;

/**
 * Yon ajan ki sove nan baz done a.
 *
 * Modèl la pote sèlman sa ki pwòp a biznis lan. Konsiy la, zouti yo ak
 * politik konfimasyon an rekonstwi depi modèl sektè a chak fwa — se sa ki
 * pèmèt amelyore yon konsiy yon sèl kote pou tout ajan yo alafwa.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $sector
 * @property array<string, mixed>|null $knowledge
 * @property list<string>|null $channels
 * @property list<string>|null $languages
 * @property string|null $handoff_to
 */
class Agent extends Model
{
    protected $fillable = [
        'key', 'name', 'sector', 'knowledge', 'channels', 'languages', 'handoff_to',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'knowledge' => 'array',
            'channels' => 'array',
            'languages' => 'array',
        ];
    }

    /** Rekonstwi ajan an konplè (konsiy + zouti + konfimasyon) depi sektè a. */
    public function toDefinition(AgentTemplateRegistry $templates): AgentDefinition
    {
        return $templates->make(
            sector: $this->sector,
            key: $this->key,
            name: $this->name,
            knowledge: $this->knowledge ?? [],
            channels: $this->channels ?: ['whatsapp'],
            languages: $this->languages ?: ['ht', 'fr'],
            handoffTo: $this->handoff_to,
        );
    }

    /**
     * Yon kle inik ki soti nan non an. Nou ajoute yon sifiks sèlman lè sa
     * nesesè: de restoran ka byen rele « Chez Nou ».
     */
    public static function uniqueKeyFor(string $name): string
    {
        $base = Str::slug($name) ?: 'ajan';
        $key = $base;
        $n = 2;

        while (static::query()->where('key', $key)->exists()) {
            $key = $base.'-'.$n;
            $n++;
        }

        return $key;
    }
}
