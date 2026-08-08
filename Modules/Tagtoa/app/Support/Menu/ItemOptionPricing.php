<?php

namespace Modules\Tagtoa\App\Support\Menu;

/**
 * TAGTOA MENU — calcule le supplément de prix + snapshot des options choisies
 * pour une ligne de commande. PUR (tableaux simples, pas d'Eloquent) : testable
 * sans Laravel.
 *
 * Format $groups : [['id'=>1,'name'=>'Taille','required'=>true,'multiple'=>false,
 *   'choices'=>[['id'=>10,'label'=>'Grand','price_delta'=>50.0], ...]], ...]
 */
class ItemOptionPricing
{
    public const ERR_MISSING_REQUIRED = 'missing_required_option';

    /** @return array{0: float, 1: array} [supplément total, snapshot des choix] */
    public static function resolve(array $groups, array $selectedIds): array
    {
        $selected = array_map('intval', $selectedIds);
        $extra = 0.0;
        $snapshot = [];

        foreach ($groups as $group) {
            $choices = $group['choices'] ?? [];
            $choiceIds = array_column($choices, 'id');
            $chosen = array_values(array_intersect($choiceIds, $selected));

            if (! empty($group['required']) && empty($chosen)) {
                throw new \RuntimeException(self::ERR_MISSING_REQUIRED);
            }
            if (empty($group['multiple']) && count($chosen) > 1) {
                $chosen = [$chosen[0]];
            }

            foreach ($chosen as $cid) {
                $choice = self::findChoice($choices, $cid);
                if (! $choice) {
                    continue;
                }
                $extra += (float) $choice['price_delta'];
                $snapshot[] = [
                    'group'       => $group['name'] ?? '',
                    'label'       => $choice['label'] ?? '',
                    'price_delta' => (float) $choice['price_delta'],
                ];
            }
        }

        return [$extra, $snapshot];
    }

    private static function findChoice(array $choices, int $id): ?array
    {
        foreach ($choices as $c) {
            if ((int) ($c['id'] ?? 0) === $id) {
                return $c;
            }
        }

        return null;
    }
}
