<?php

namespace Modules\AIProvider\Registry;

use Modules\AIProvider\Contracts\AIProvider;
use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\Enums\Capability;

/**
 * Découverte des connecteurs déclarés en configuration. C'est le seul endroit
 * qui connaît la liste des fournisseurs ; tout le reste du système travaille
 * sur l'interface AIProvider.
 */
class ProviderRegistry
{
    /** @var array<string, AIProvider> */
    private array $providers = [];

    /** @param array<string, array<string, mixed>> $definitions */
    public function __construct(array $definitions = [])
    {
        foreach ($definitions as $key => $definition) {
            $enabled = $definition['enabled'] ?? true;

            if ($enabled === false || $enabled === '0' || $enabled === 0) {
                continue;
            }

            $class = $definition['class'] ?? null;

            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            /** @var AIProvider $provider */
            $provider = new $class($definition);

            $this->providers[$provider->key()] = $provider;
        }
    }

    /** Enregistrement dynamique (tests, fournisseurs privés d'un client). */
    public function register(AIProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function forget(string $key): void
    {
        unset($this->providers[$key]);
    }

    /** @return list<AIProvider> */
    public function all(): array
    {
        return array_values($this->providers);
    }

    /** @return list<AIProvider> fournisseurs disposant réellement d'identifiants */
    public function configured(): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn (AIProvider $provider): bool => $provider->isConfigured(),
        ));
    }

    public function get(string $key): ?AIProvider
    {
        return $this->providers[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /**
     * Catalogue agrégé des modèles disponibles pour une capacité donnée.
     *
     * @return list<ModelDescriptor>
     */
    public function modelsFor(Capability $capability, bool $configuredOnly = true): array
    {
        $models = [];

        foreach ($configuredOnly ? $this->configured() : $this->all() as $provider) {
            foreach ($provider->models() as $model) {
                if ($model->supports($capability)) {
                    $models[] = $model;
                }
            }
        }

        return $models;
    }

    /**
     * Résout un identifiant public « fournisseur/modèle ». Accepte aussi un
     * nom de modèle nu (« gpt-4o-mini ») : le premier fournisseur qui le
     * propose l'emporte, ce qui rend l'API compatible avec les SDK OpenAI.
     */
    public function findModel(string $publicId, bool $configuredOnly = true): ?ModelDescriptor
    {
        $providers = $configuredOnly ? $this->configured() : $this->all();

        [$providerKey, $modelKey] = str_contains($publicId, '/')
            ? explode('/', $publicId, 2)
            : [null, $publicId];

        foreach ($providers as $provider) {
            if ($providerKey !== null && $provider->key() !== $providerKey) {
                continue;
            }

            foreach ($provider->models() as $model) {
                if ($model->key === $modelKey || $model->publicId() === $publicId) {
                    return $model;
                }
            }
        }

        // « openrouter/meta-llama/llama-3.3-70b » : la clé du modèle contient
        // elle-même des barres obliques.
        if ($providerKey !== null) {
            $provider = $this->get($providerKey);

            if ($provider !== null && (! $configuredOnly || $provider->isConfigured())) {
                foreach ($provider->models() as $model) {
                    if ($model->key === $modelKey) {
                        return $model;
                    }
                }
            }
        }

        return null;
    }
}
