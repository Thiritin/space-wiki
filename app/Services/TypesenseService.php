<?php

namespace App\Services;

use Typesense\Client;
use Illuminate\Support\Facades\Log;

class TypesenseService
{
    private Client $client;
    private string $collectionName;

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

        $this->collectionName = config('wiki.typesense.collection_name');
    }

    public function createCollectionIfNotExists(): bool
    {
        try {
            $this->client->collections[$this->collectionName]->retrieve();
            return true;
        } catch (\Exception $e) {
            return $this->createCollection();
        }
    }

    private function createCollection(): bool
    {
        try {
            $schema = [
                'name' => $this->collectionName,
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
            Log::info('Typesense collection created successfully', ['collection' => $this->collectionName]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create Typesense collection', [
                'collection' => $this->collectionName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function indexDocument(array $document): bool
    {
        try {
            $this->client->collections[$this->collectionName]->documents->create($document);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to index document in Typesense', [
                'document_id' => $document['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function updateDocument(array $document): bool
    {
        try {
            $this->client->collections[$this->collectionName]->documents[$document['id']]->update($document);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update document in Typesense', [
                'document_id' => $document['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function deleteDocument(string $documentId): bool
    {
        try {
            $this->client->collections[$this->collectionName]->documents[$documentId]->delete();
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete document from Typesense', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function documentExists(string $documentId): bool
    {
        try {
            $this->client->collections[$this->collectionName]->documents[$documentId]->retrieve();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function search(string $query, array $options = []): array
    {
        try {
            $searchParams = array_merge([
                'q' => $query,
                'query_by' => 'title,content',
                'sort_by' => 'last_modified:desc',
            ], $options);

            $result = $this->client->collections[$this->collectionName]->documents->search($searchParams);
            return $result['hits'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to search Typesense', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getCollectionInfo(): ?array
    {
        try {
            return $this->client->collections[$this->collectionName]->retrieve();
        } catch (\Exception $e) {
            Log::error('Failed to get collection info', [
                'collection' => $this->collectionName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}