<?php

namespace Modules\AIProvider\Registry;

use Illuminate\Support\Facades\Schema;
use Modules\AIProvider\Models\AiProviderRecord;
use Throwable;

/**
 * Kle API yo ki sere nan baz la, pou konplete sa ki nan `.env`.
 *
 * **Règ presedans: `.env` PI FÒ pase baz la.** Yon kle yon ekip mete espre
 * sou sèvè a pa dwe ka ranplase depi yon paj wèb. Baz la sèvi pou sa `.env`
 * la pa genyen — sa vle di yon fondatè ka limen yon founisè san yon
 * deplwaman, men li pa ka koupe yon founisè enfrastrikti san touche sèvè a.
 *
 * **Tolerab**: si tab la poko egziste oswa baz la pa reponn, nou retounen
 * yon tablo vid. Platfòm nan dwe leve menm lè baz la poko migre.
 */
class CredentialStore
{
    /** @var array<string, string>|null */
    private ?array $cache = null;

    /** @return array<string, string> kle founisè => kle API */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            if (! Schema::hasTable('ai_providers')) {
                return $this->cache = [];
            }

            $keys = [];

            foreach (AiProviderRecord::query()->whereNotNull('api_key')->get() as $record) {
                $value = trim((string) $record->api_key);

                if ($value !== '') {
                    $keys[$record->key] = $value;
                }
            }

            return $this->cache = $keys;
        } catch (Throwable) {
            return $this->cache = [];
        }
    }

    /**
     * Konplete definisyon config yo ak kle baz la — san janm ranplase youn
     * ki deja la.
     *
     * @param  array<string, array<string, mixed>>  $definitions
     * @return array<string, array<string, mixed>>
     */
    public function merge(array $definitions): array
    {
        $stored = $this->all();

        foreach ($definitions as $key => $definition) {
            $fromEnv = $definition['api_key'] ?? '';
            $hasEnv = is_string($fromEnv) && trim($fromEnv) !== '';

            if (! $hasEnv && isset($stored[$key])) {
                $definitions[$key]['api_key'] = $stored[$key];
            }
        }

        return $definitions;
    }

    public function forget(): void
    {
        $this->cache = null;
    }
}
