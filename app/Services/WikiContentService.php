<?php

namespace App\Services;

use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WikiContentService
{
    private DokuWikiService $dokuWikiService;

    public function __construct(DokuWikiService $dokuWikiService)
    {
        $this->dokuWikiService = $dokuWikiService;
    }

    public function syncAllPages(): array
    {
        $stats = [
            'processed' => 0,
            'indexed' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        try {
            $pages = $this->dokuWikiService->getAllPages();
            $totalPages = count($pages);
            Log::info('Starting wiki content sync', ['total_pages' => $totalPages]);

            $batchSize = 50; // Process in batches to prevent timeouts
            $batches = array_chunk($pages, $batchSize);
            
            echo "Found {$totalPages} pages, processing in " . count($batches) . " batches of {$batchSize}...\n";

            foreach ($batches as $batchIndex => $batch) {
                echo "Processing batch " . ($batchIndex + 1) . "/" . count($batches) . "...\n";
                
                foreach ($batch as $page) {
                    $stats['processed']++;
                    
                    if ($this->syncPage($page['id'])) {
                        $existingPage = Page::findByPageId($page['id']);
                        if ($existingPage && $existingPage->wasRecentlyCreated) {
                            $stats['indexed']++;
                        } else {
                            $stats['updated']++;
                        }
                    } else {
                        $stats['errors']++;
                    }

                    // Progress indicator
                    if ($stats['processed'] % 25 === 0) {
                        echo "Progress: {$stats['processed']}/{$totalPages} pages processed\n";
                    }
                }
                
                // Small delay between batches to prevent overloading
                usleep(100000); // 0.1 second
            }

            $this->updateLastSyncTime();
            Log::info('Wiki content sync completed', $stats);

        } catch (\Exception $e) {
            Log::error('Wiki content sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $stats;
    }

    public function syncUpdatedPages(): array
    {
        $stats = [
            'processed' => 0,
            'indexed' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        try {
            $lastSync = $this->getLastSyncTime();
            $recentChanges = $this->dokuWikiService->getRecentChanges($lastSync);
            
            Log::info('Starting incremental wiki sync', [
                'last_sync' => $lastSync,
                'recent_changes' => count($recentChanges)
            ]);

            foreach ($recentChanges as $change) {
                $stats['processed']++;
                
                if ($this->syncPage($change['id'])) {
                    $existingPage = Page::findByPageId($change['id']);
                    if ($existingPage && $existingPage->wasRecentlyCreated) {
                        $stats['indexed']++;
                    } else {
                        $stats['updated']++;
                    }
                } else {
                    $stats['errors']++;
                }
            }

            $this->updateLastSyncTime();
            Log::info('Incremental wiki sync completed', $stats);

        } catch (\Exception $e) {
            Log::error('Incremental wiki sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $stats;
    }

    private function syncPage(string $pageId): bool
    {
        try {
            $pageInfo = $this->dokuWikiService->getPageInfo($pageId);
            
            if (empty($pageInfo)) {
                Log::warning('Page info not found', ['page_id' => $pageId]);
                return false;
            }

            $content = $this->dokuWikiService->getPage($pageId);
            $htmlContent = $this->dokuWikiService->getPageHtml($pageId);
            
            if (empty($content)) {
                Log::warning('Page content is empty', ['page_id' => $pageId]);
                return false;
            }

            // Extract additional data
            $tableOfContents = $this->dokuWikiService->extractTableOfContents($htmlContent);
            $excerpt = $this->dokuWikiService->generateExcerpt($content);

            $page = Page::createFromDokuWiki(
                $pageId, 
                $pageInfo, 
                $content, 
                $htmlContent,
                $tableOfContents,
                $excerpt
            );
            $page->searchable();
            
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to sync page', [
                'page_id' => $pageId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }


    private function getLastSyncTime(): int
    {
        return Cache::get('wiki_last_sync_time', 0);
    }

    private function updateLastSyncTime(): void
    {
        Cache::forever('wiki_last_sync_time', time());
    }

    public function getStats(): array
    {
        try {
            $documentCount = Page::count();
            
            return [
                'collection_exists' => true,
                'document_count' => $documentCount,
                'last_sync' => $this->getLastSyncTime(),
                'last_sync_human' => $this->getLastSyncTime() > 0 
                    ? Carbon::createFromTimestamp($this->getLastSyncTime())->diffForHumans()
                    : 'Never',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get wiki stats', ['error' => $e->getMessage()]);
            return [
                'collection_exists' => false,
                'document_count' => 0,
                'last_sync' => 0,
                'last_sync_human' => 'Error',
            ];
        }
    }
}