<?php

namespace Modules\AIServices\Catalog\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\Enums\Capability;
use Modules\AIProvider\Registry\ModelCatalog;

/**
 * Catalogue agrégé multi-fournisseurs, au format OpenAI `GET /v1/models`,
 * enrichi des informations propres à GOVIBE (prix, qualité par langue).
 */
class ModelsController extends Controller
{
    public function __construct(private readonly ModelCatalog $catalog) {}

    public function __invoke(): JsonResponse
    {
        $models = [];

        foreach (Capability::cases() as $capability) {
            foreach ($this->catalog->forCapability($capability) as $model) {
                $models[$model->publicId()] = $this->present($model);
            }
        }

        return response()->json([
            'object' => 'list',
            'data' => array_values($models),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(ModelDescriptor $model): array
    {
        return [
            'id' => $model->publicId(),
            'object' => 'model',
            'owned_by' => $model->providerKey,
            'name' => $model->name ?? $model->key,
            'context_window' => $model->contextWindow,
            'capabilities' => array_map(
                static fn (Capability $capability): string => $capability->value,
                $model->capabilities,
            ),
            'pricing' => [
                // Micro-USD par million de jetons : entiers, pas de flottants.
                'input_per_million_micro_usd' => $model->inputPricePerMillion,
                'output_per_million_micro_usd' => $model->outputPricePerMillion,
            ],
            'quality' => $model->quality,
            'quality_by_language' => $model->qualityByLanguage,
        ];
    }
}
