<?php

namespace Modules\AIProvider\Connectors;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\AIProvider\Contracts\AIProvider;
use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\Enums\Capability;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;

/**
 * Socle commun aux connecteurs : chargement du manifeste, identifiants et
 * plomberie HTTP. Toute la traduction de protocole vit dans les traducteurs
 * (classes pures) ; ici on ne fait que de l'E/S.
 */
abstract class BaseProvider implements AIProvider
{
    /** @var list<ModelDescriptor>|null */
    private ?array $models = null;

    /** @var array<string, mixed>|null */
    private ?array $manifest = null;

    /** @param array<string, mixed> $config identifiants et surcharges (config/.env) */
    public function __construct(protected array $config = []) {}

    public function name(): string
    {
        $name = $this->manifest()['name'] ?? null;

        return is_string($name) ? $name : ucfirst($this->key());
    }

    /** @return list<Capability> */
    public function capabilities(): array
    {
        $capabilities = [];

        foreach ($this->models() as $model) {
            foreach ($model->capabilities as $capability) {
                $capabilities[$capability->value] = $capability;
            }
        }

        return array_values($capabilities);
    }

    /** @return list<ModelDescriptor> */
    public function models(): array
    {
        if ($this->models !== null) {
            return $this->models;
        }

        $declared = $this->manifest()['models'] ?? [];
        $models = [];

        foreach (is_array($declared) ? $declared : [] as $entry) {
            if (is_array($entry) && isset($entry['key'])) {
                $models[] = ModelDescriptor::fromManifest($this->key(), $entry);
            }
        }

        return $this->models = $models;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function apiKey(): string
    {
        $key = $this->config['api_key'] ?? '';

        return is_string($key) ? trim($key) : '';
    }

    public function baseUrl(): string
    {
        $url = $this->config['base_url'] ?? null;

        return rtrim(is_string($url) && $url !== '' ? $url : $this->defaultBaseUrl(), '/');
    }

    abstract protected function defaultBaseUrl(): string;

    /** @return array<string, string> */
    abstract protected function headers(): array;

    protected function timeout(): int
    {
        $timeout = $this->config['timeout'] ?? null;

        return is_numeric($timeout) ? (int) $timeout : 120;
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders($this->headers())
            ->timeout($this->timeout())
            ->connectTimeout(10)
            ->acceptJson();
    }

    /**
     * Manifeste du connecteur : `manifest.php` posé à côté de la classe.
     * Ainsi, ajouter un fournisseur = un dossier, sans toucher au cœur.
     *
     * @return array<string, mixed>
     */
    protected function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $file = (new ReflectionClass($this))->getFileName();
        $path = $file === false ? null : dirname($file).'/manifest.php';

        if ($path === null || ! is_file($path)) {
            return $this->manifest = [];
        }

        $manifest = require $path;

        return $this->manifest = is_array($manifest) ? $manifest : [];
    }

    /**
     * Découpe un corps HTTP en lignes au fil de l'eau, sans jamais charger la
     * réponse entière en mémoire (indispensable pour la diffusion SSE).
     *
     * @return Generator<int, string>
     */
    protected function streamLines(StreamInterface $body): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($position = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $position);
                $buffer = substr($buffer, $position + 1);

                yield $line;
            }
        }

        if (trim($buffer) !== '') {
            yield $buffer;
        }
    }
}
