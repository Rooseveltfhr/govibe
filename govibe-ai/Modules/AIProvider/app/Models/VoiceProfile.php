<?php

namespace Modules\AIProvider\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Yon vwa nan bibliyotèk platfòm nan.
 *
 * @property string $provider_key
 * @property string $voice_id
 * @property string $name
 * @property string $language
 * @property string $source
 * @property bool $is_default
 * @property string|null $preview_url
 */
class VoiceProfile extends Model
{
    public const LANGUAGES = ['ht', 'fr', 'en', 'es'];

    public const SOURCE_LIBRARY = 'library';

    public const SOURCE_CLONED = 'cloned';

    protected $fillable = [
        'provider_key', 'voice_id', 'name', 'language', 'source',
        'is_default', 'preview_url', 'notes', 'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'position' => 'integer'];
    }

    /**
     * Fè vwa sa a vin vwa pa defo pou lang li a.
     *
     * Yon sèl pa lang: si de vwa te make defo pou kreyòl, ajan an t ap pran
     * youn nan de a san nou konnen kilès — epi biznis lan ta chanje vwa san
     * pèsonn pa touche anyen.
     */
    public function makeDefault(): void
    {
        static::query()
            ->where('language', $this->language)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    public function isCloned(): bool
    {
        return $this->source === self::SOURCE_CLONED;
    }
}
