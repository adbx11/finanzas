<?php

namespace App\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesTableSorting
{
    protected function sortDirection(Request $request): string
    {
        return strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
    }

    protected function sortColumn(Request $request): ?string
    {
        $sort = $request->input('sort');

        return is_string($sort) && $sort !== '' ? $sort : null;
    }

    protected function sortingParams(Request $request): array
    {
        return array_filter([
            'sort' => $this->sortColumn($request),
            'direction' => $this->sortColumn($request) ? $this->sortDirection($request) : null,
        ]);
    }

    /**
     * @param  array<string, callable(Builder, string): void>  $sortMap
     */
    protected function applyTableSorting(
        Builder $query,
        Request $request,
        array $sortMap,
        ?callable $default = null,
    ): void {
        $sort = $this->sortColumn($request);
        $direction = $this->sortDirection($request);

        if ($sort && isset($sortMap[$sort])) {
            $sortMap[$sort]($query, $direction);

            if ($query->getQuery()->orders === null) {
                $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
            }

            return;
        }

        if ($default) {
            $default($query);
        }
    }
}
