<?php

namespace Modules\AIProvider\Registry;

use Illuminate\Support\Facades\Schema;
use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\Enums\Capability;
use Modules\AIProvider\Models\AiModelRecord;
use Throwable;

/**
 * Catalogue effectif = manifestes des connecteurs + surcharges en base.
 *
 * Le manifeste fournit les valeurs par défaut livrées avec le code ; la table
 * `ai_models` permet d'ajuster prix, qualité et activation depuis
 * l'administration sans redéployer. Si la table n'existe pas encore (ou que la
 * base est indisponible), on retombe proprement sur les manifestes.
 */
class ModelCatalog
{
    /** @var array<string, AiModelRecord>|null */
    private ?array $overrides = null;

    public function __construct(private readonly ProviderRegistry $registry) {}

    /** @return list<ModelDescriptor> */
    public function forCapability(Capability $capability, bool $configuredOnly = true): array
    {
        $models = [];

        foreach ($this->registry->modelsFor($capability, $configuredOnly) as $model) {
            $effective = $this->applyOverride($model);

            if ($effective !== null) {
                $models[] = $effective;
            }
        }

        return $models;
    }

    public function find(string $publicId, bool $configuredOnly = true): ?ModelDescriptor
    {
        $model = $this->registry->findModel($publicId, $configuredOnly);

        return $model === null ? null : $this->applyOverride($model);
    }

    /** Retourne null si le modèle a été désactivé en base. */
    private function applyOverride(ModelDescriptor $model): ?ModelDescriptor
    {
        $record = $this->overrides()[$model->providerKey.'|'.$model->key] ?? null;

        if ($record === null) {
            return $model;
        }

        if (! $record->is_active) {
            return null;
        }

        /** @var array<string, int> $qualityByLanguage */
        $qualityByLanguage = is_array($record->quality_by_language)
            ? array_map('intval', $record->quality_by_language)
            : $model->qualityByLanguage;

        return new ModelDescriptor(
            key: $model->key,
            providerKey: $model->providerKey,
            capabilities: $model->capabilities,
            inputPricePerMillion: (int) $record->getAttribute('input_price_per_million'),
            outputPricePerMillion: (int) $record->getAttribute('output_price_per_million'),
            contextWindow: (int) $record->getAttribute('context_window'),
            quality: (int) $record->getAttribute('quality'),
            qualityByLanguage: $qualityByLanguage,
            expectedLatencyMs: (int) $record->getAttribute('expected_latency_ms'),
            name: $model->name,
        );
    }

    /** @return array<string, AiModelRecord> */
    private function overrides(): array
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        try {
            if (! Schema::hasTable('ai_models')) {
                return $this->overrides = [];
            }

            $records = AiModelRecord::query()->get();
        } catch (Throwable) {
            // Base indisponible : le catalogue reste servi par les manifestes.
            return $this->overrides = [];
        }

        $indexed = [];

        foreach ($records as $record) {
            $indexed[$record->provider_key.'|'.$record->key] = $record;
        }

        return $this->overrides = $indexed;
    }
}
