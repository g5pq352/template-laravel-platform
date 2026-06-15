<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class HierarchicalTreeService
{
    public function normalizeSortOrder(Builder $scope, string $orderColumn = 'sort_order', string $keyColumn = 'id'): void
    {
        $ids = (clone $scope)
            ->orderBy($orderColumn)
            ->orderBy($keyColumn)
            ->pluck($keyColumn)
            ->all();

        foreach (array_values($ids) as $index => $id) {
            (clone $scope)->whereKey($id)->update([$orderColumn => $index + 1]);
        }
    }

    public function reorder(Model $movingItem, Builder $scope, int $targetPosition, string $orderColumn = 'sort_order'): void
    {
        $this->normalizeSortOrder($scope, $orderColumn, $movingItem->getKeyName());

        $ids = (clone $scope)
            ->whereKeyNot($movingItem->getKey())
            ->orderBy($orderColumn)
            ->orderBy($movingItem->getKeyName())
            ->pluck($movingItem->getKeyName())
            ->all();

        $targetIndex = max(0, min($targetPosition - 1, count($ids)));
        array_splice($ids, $targetIndex, 0, [$movingItem->getKey()]);

        foreach (array_values($ids) as $index => $id) {
            (clone $scope)->whereKey($id)->update([$orderColumn => $index + 1]);
        }
    }

    public function descendantIds(Collection $items, int $parentId, string $parentColumn = 'parent_id', string $keyColumn = 'id'): Collection
    {
        $children = $items->where($parentColumn, $parentId);

        return $children->pluck($keyColumn)->merge(
            $children->flatMap(fn (Model $item) => $this->descendantIds($items, (int) $item->getAttribute($keyColumn), $parentColumn, $keyColumn))
        )->values();
    }

    public function flattenedOptions(Collection $items, ?int $excludeId = null, string $labelMethod = 'pathLabel'): array
    {
        $excluded = $excludeId ? $this->descendantIds($items, $excludeId)->push($excludeId)->all() : [];

        return $items
            ->reject(fn (Model $item) => in_array($item->getKey(), $excluded, true))
            ->map(fn (Model $item) => [
                'id' => $item->getKey(),
                'label' => method_exists($item, $labelMethod) ? $item->{$labelMethod}() : (string) $item->getKey(),
            ])
            ->values()
            ->all();
    }
}
