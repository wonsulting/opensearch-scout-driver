<?php declare(strict_types=1);

namespace OpenSearch\ScoutDriver\Factories;

use InvalidArgumentException;
use Laravel\Scout\Builder;
use OpenSearch\Adapter\Search\SearchParameters;
use stdClass;

class SearchParametersFactory implements SearchParametersFactoryInterface
{
    public function makeFromBuilder(Builder $builder, array $options = []): SearchParameters
    {
        $searchParameters = new SearchParameters();

        $index = $this->makeIndex($builder);
        $searchParameters->indices([$index]);

        $query = $this->makeQuery($builder);
        $searchParameters->query($query);

        if ($sort = $this->makeSort($builder)) {
            $searchParameters->sort($sort);
        }

        if ($from = $this->makeFrom($options)) {
            $searchParameters->from($from);
        }

        if ($size = $this->makeSize($builder, $options)) {
            $searchParameters->size($size);
        }

        return $searchParameters;
    }

    protected function makeIndex(Builder $builder): string
    {
        return $builder->index ?: $builder->model->searchableAs();
    }

    protected function makeQuery(Builder $builder): array
    {
        $query = [
            'bool' => [],
        ];

        if (!empty($builder->query)) {
            $query['bool']['must'] = [
                'query_string' => [
                    'query' => $builder->query,
                ],
            ];
        } else {
            $query['bool']['must'] = [
                'match_all' => new stdClass(),
            ];
        }

        if ($filter = $this->makeFilter($builder)) {
            $query['bool']['filter'] = $filter;
        }

        return $query;
    }

    protected function makeFilter(Builder $builder): ?array
    {
        $filter = collect($builder->wheres)->map(static function ($value, $field) {
            // Scout >= 11 stores each where as ['field' => ..., 'operator' => ..., 'value' => ...];
            // Scout <= 10 stores them as [field => value]. Both map to an equality term filter.
            if (is_array($value) && array_key_exists('field', $value)) {
                if (($value['operator'] ?? '=') !== '=') {
                    throw new InvalidArgumentException(sprintf(
                        'The "%s" operator is not supported by the OpenSearch Scout driver, only "=" is.',
                        (string)$value['operator']
                    ));
                }

                return ['term' => [$value['field'] => $value['value']]];
            }

            return ['term' => [$field => $value]];
        })->values();

        if (property_exists($builder, 'whereIns')) {
            $whereIns = collect($builder->whereIns)->map(static fn (array $values, string $field) => [
                'terms' => [$field => $values],
            ])->values();

            $filter = $filter->merge($whereIns);
        }

        return $filter->isEmpty() ? null : $filter->all();
    }

    protected function makeSort(Builder $builder): ?array
    {
        $sort = collect($builder->orders)->map(static fn (array $order) => [
            $order['column'] => $order['direction'],
        ]);

        return $sort->isEmpty() ? null : $sort->all();
    }

    protected function makeFrom(array $options): ?int
    {
        if (isset($options['page'], $options['perPage'])) {
            return ($options['page'] - 1) * $options['perPage'];
        }

        return null;
    }

    protected function makeSize(Builder $builder, array $options): ?int
    {
        return $options['perPage'] ?? $builder->limit;
    }
}
