<?php

use App\Services\WikiContentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('wiki:sync-all', function (WikiContentService $wikiContentService) {
    $this->info('Starting full wiki content sync...');
    
    try {
        $stats = $wikiContentService->syncAllPages();
        
        $this->info('Wiki sync completed successfully!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Processed', $stats['processed']],
                ['Indexed', $stats['indexed']],
                ['Updated', $stats['updated']],
                ['Errors', $stats['errors']],
            ]
        );
        
        if ($stats['errors'] > 0) {
            $this->warn("Sync completed with {$stats['errors']} errors. Check logs for details.");
            return 1;
        }
        
        return 0;
    } catch (\Exception $e) {
        $this->error("Wiki sync failed: {$e->getMessage()}");
        return 1;
    }
})->purpose('Sync all wiki pages to database and Typesense search index for frontend and search');

Artisan::command('wiki:sync-updates', function (WikiContentService $wikiContentService) {
    $this->info('Starting incremental wiki content sync...');
    
    try {
        $stats = $wikiContentService->syncUpdatedPages();
        
        $this->info('Incremental wiki sync completed successfully!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Processed', $stats['processed']],
                ['Indexed', $stats['indexed']],
                ['Updated', $stats['updated']],
                ['Errors', $stats['errors']],
            ]
        );
        
        if ($stats['errors'] > 0) {
            $this->warn("Sync completed with {$stats['errors']} errors. Check logs for details.");
            return 1;
        }
        
        return 0;
    } catch (\Exception $e) {
        $this->error("Incremental wiki sync failed: {$e->getMessage()}");
        return 1;
    }
})->purpose('Sync only updated wiki pages to database and Typesense search index for frontend and search');

Artisan::command('wiki:stats', function (WikiContentService $wikiContentService) {
    $this->info('Wiki Search Statistics');
    
    $stats = $wikiContentService->getStats();
    
    $this->table(
        ['Metric', 'Value'],
        [
            ['Collection Exists', $stats['collection_exists'] ? 'Yes' : 'No'],
            ['Indexed Documents', $stats['document_count']],
            ['Last Sync', $stats['last_sync_human']],
        ]
    );
})->purpose('Display wiki database and search index statistics');

Schedule::command('wiki:sync-updates')
    ->everyMinute();
