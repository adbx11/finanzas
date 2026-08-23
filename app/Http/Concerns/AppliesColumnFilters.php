<?php

namespace App\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesColumnFilters
{
    protected function filterValues(Request $request): array
    {
        $filters = $request->input('filters', []);

        return is_array($filters) ? $filters : [];
    }

    protected function filterString(Request $request, string $key): ?string
    {
        $value = trim((string) ($this->filterValues($request)[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    protected function applyLikeFilter(Builder $query, ?string $value, string $column): void
    {
        if ($value !== null) {
            $query->where($column, 'like', '%'.$value.'%');
        }
    }

    protected function applyRelationLikeFilter(Builder $query, ?string $value, string $relation, string $column): void
    {
        if ($value !== null) {
            $query->whereHas($relation, fn (Builder $builder) => $builder->where($column, 'like', '%'.$value.'%'));
        }
    }

    protected function applyRelationMultiLikeFilter(Builder $query, ?string $value, string $relation, array $columns): void
    {
        if ($value !== null) {
            $query->whereHas($relation, function (Builder $builder) use ($value, $columns) {
                $builder->where(function (Builder $nested) use ($value, $columns) {
                    foreach ($columns as $column) {
                        $nested->orWhere($column, 'like', '%'.$value.'%');
                    }
                });
            });
        }
    }
}
