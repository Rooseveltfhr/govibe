<?php

namespace Modules\AIProvider\Console;

use Illuminate\Console\Command;
use Modules\AIProvider\Models\AiModelRecord;
use Modules\AIProvider\Models\AiProviderRecord;
use Modules\AIProvider\Registry\ProviderRegistry;

/**
 * Recopie les manifestes des connecteurs dans `ai_providers` / `ai_models`
 * afin qu'ils deviennent modifiables depuis l'administration.
 *
 * Idempotent : relançable à chaque déploiement. Les valeurs déjà ajustées en
 * base ne sont écrasées qu'avec `--force`.
 */
class SyncCatalogCommand extends Command
{
    protected $signature = 'govibe:catalog-sync {--force : écraser les valeurs ajustées en base}';

    protected $description = 'Synchronise le catalogue des fournisseurs et modèles depuis les manifestes';

    public function handle(ProviderRegistry $registry): int
    {
        $force = (bool) $this->option('force');
        $providerCount = 0;
        $modelCount = 0;

        foreach ($registry->all() as $provider) {
            $record = AiProviderRecord::query()->firstOrNew(['key' => $provider->key()]);
            $record->fill(['name' => $provider->name()]);

            if (! $record->exists) {
                $record->fill(['status' => 'active', 'priority' => 100]);
            }

            $record->save();
            $providerCount++;

            foreach ($provider->models() as $model) {
                $modelRecord = AiModelRecord::query()->firstOrNew([
                    'provider_key' => $model->providerKey,
                    'key' => $model->key,
                ]);

                if ($modelRecord->exists && ! $force) {
                    // Ne jamais écraser un prix ajusté par un administrateur.
                    $modelRecord->fill(['ai_provider_id' => $record->getKey()])->save();
                    $modelCount++;

                    continue;
                }

                $modelRecord->fill([
                    'ai_provider_id' => $record->getKey(),
                    'name' => $model->name,
                    'capabilities' => array_map(
                        static fn ($capability): string => $capability->value,
                        $model->capabilities,
                    ),
                    'input_price_per_million' => $model->inputPricePerMillion,
                    'output_price_per_million' => $model->outputPricePerMillion,
                    'context_window' => $model->contextWindow,
                    'quality' => $model->quality,
                    'quality_by_language' => $model->qualityByLanguage,
                    'expected_latency_ms' => $model->expectedLatencyMs,
                    'is_active' => true,
                ])->save();

                $modelCount++;
            }
        }

        $this->info("Catalogue synchronisé : {$providerCount} fournisseurs, {$modelCount} modèles.");

        return self::SUCCESS;
    }
}
