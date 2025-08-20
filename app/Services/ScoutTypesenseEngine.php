<?php

namespace App\Services;

use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Typesense\Client;

class ScoutTypesenseEngine extends Engine
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'nodes' => [
                [
                    'host' => config('wiki.typesense.host'),
                    'port' => config('wiki.typesense.port'),
                    'protocol' => config('wiki.typesense.protocol'),
                ],
            ],
            'api_key' => config('wiki.typesense.api_key'),
            'connection_timeout_seconds' => 2,
        ]);
    }

    public function update($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $collectionName = $models->first()->searchableAs();
        $this->createCollectionIfNotExists($collectionName);

        $models->each(function ($model) use ($collectionName) {
            try {
                $document = $model->toSearchableArray();
                $this->client->collections[$collectionName]->documents->upsert($document);
            } catch (\Exception $e) {
                // Log error but don't fail the entire operation
                \Log::error('Failed to index document', [
                    'model' => get_class($model),
                    'key' => $model->getScoutKey(),
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $collectionName = $models->first()->searchableAs();

        $models->each(function ($model) use ($collectionName) {
            try {
                $this->client->collections[$collectionName]->documents[$model->getScoutKey()]->delete();
            } catch (\Exception $e) {
                // Log error but don't fail the entire operation
                \Log::error('Failed to delete document', [
                    'model' => get_class($model),
                    'key' => $model->getScoutKey(),
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'q' => $builder->query,
            'query_by' => 'title,content',
            'sort_by' => 'last_modified:desc',
            'per_page' => $builder->limit ?: 50,
            'page' => 1,
        ]));
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, array_filter([
            'q' => $builder->query,
            'query_by' => 'title,content',
            'sort_by' => 'last_modified:desc',
            'per_page' => $perPage,
            'page' => $page,
        ]));
    }

    protected function performSearch(Builder $builder, array $searchParams = [])
    {
        try {
            $collectionName = $builder->model->searchableAs();
            $result = $this->client->collections[$collectionName]->documents->search($searchParams);
            
            return $result;
        } catch (\Exception $e) {
            \Log::error('Search failed', [
                'query' => $builder->query,
                'error' => $e->getMessage()
            ]);
            
            return [
                'hits' => [],
                'found' => 0,
                'out_of' => 0,
                'page' => 1,
                'search_time_ms' => 0,
            ];
        }
    }

    public function mapIds($results): BaseCollection
    {
        return collect($results['hits'] ?? [])->pluck('document.id');
    }

    public function map(Builder $builder, $results, $model): Collection
    {
        if (empty($results['hits'])) {
            return $model->newCollection();
        }

        $objectIds = collect($results['hits'])->pluck('document.id');
        $objectIdPositions = array_flip($objectIds->toArray());

        return $model->getScoutModelsByIds($builder, $objectIds->values()->all())
            ->sortBy(function ($model) use ($objectIdPositions) {
                return $objectIdPositions[$model->getScoutKey()] ?? 999;
            })->values();
    }

    public function getTotalCount($results): int
    {
        return $results['found'] ?? 0;
    }

    public function flush($model): void
    {
        try {
            $collectionName = $model->searchableAs();
            $this->client->collections[$collectionName]->delete();
        } catch (\Exception $e) {
            // Collection might not exist, which is fine
        }
    }

    public function lazyMap(Builder $builder, $results, $model)
    {
        return $this->map($builder, $results, $model);
    }

    public function createIndex($name, array $options = []): void
    {
        $this->createCollection($name);
    }

    public function deleteIndex($name): void
    {
        try {
            $this->client->collections[$name]->delete();
        } catch (\Exception $e) {
            // Collection might not exist, which is fine
        }
    }

    private function createCollectionIfNotExists(string $collectionName): void
    {
        try {
            $this->client->collections[$collectionName]->retrieve();
        } catch (\Exception $e) {
            $this->createCollection($collectionName);
        }
    }

    private function createCollection(string $collectionName): void
    {
        try {
            $schema = [
                'name' => $collectionName,
                'fields' => [
                    [
                        'name' => 'id',
                        'type' => 'string',
                        'facet' => false,
                    ],
                    [
                        'name' => 'title',
                        'type' => 'string',
                        'facet' => false,
                    ],
                    [
                        'name' => 'content',
                        'type' => 'string',
                        'facet' => false,
                    ],
                    [
                        'name' => 'namespace',
                        'type' => 'string',
                        'facet' => true,
                    ],
                    [
                        'name' => 'last_modified',
                        'type' => 'int64',
                        'facet' => true,
                    ],
                    [
                        'name' => 'url',
                        'type' => 'string',
                        'facet' => false,
                    ],
                ],
                'default_sorting_field' => 'last_modified',
            ];

            $this->client->collections->create($schema);
        } catch (\Exception $e) {
            \Log::error('Failed to create Typesense collection', [
                'collection' => $collectionName,
                'error' => $e->getMessage()
            ]);
        }
    }
}