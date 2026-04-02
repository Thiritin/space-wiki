<?php

use App\Models\WikiSyncLog;
use App\Services\DokuWikiService;
use App\Services\WikiContentService;

it('logs a full sync to the database', function () {
    $dokuWiki = mock(DokuWikiService::class);
    $dokuWiki->shouldReceive('getAllPages')->andReturn([
        ['id' => 'test:page1'],
    ]);
    $dokuWiki->shouldReceive('getPageInfo')->andReturn([
        'lastModified' => time(),
        'revision' => time(),
        'permission' => 1,
        'size' => 100,
    ]);
    $dokuWiki->shouldReceive('getPage')->andReturn('====== Test Page ======');
    $dokuWiki->shouldReceive('getPageHtml')->andReturn('<h1>Test Page</h1>');
    $dokuWiki->shouldReceive('extractTableOfContents')->andReturn([]);
    $dokuWiki->shouldReceive('generateExcerpt')->andReturn('Test Page');

    $service = new WikiContentService($dokuWiki);
    $stats = $service->syncAllPages();

    expect($stats['processed'])->toBe(1);
    expect($stats['errors'])->toBe(0);
    expect(WikiSyncLog::count())->toBe(1);
    expect(WikiSyncLog::first()->type)->toBe('full');
});

it('logs an incremental sync to the database', function () {
    $dokuWiki = mock(DokuWikiService::class);
    $dokuWiki->shouldReceive('getRecentChanges')->andReturn([
        ['id' => 'test:updated'],
    ]);
    $dokuWiki->shouldReceive('getPageInfo')->andReturn([
        'lastModified' => time(),
        'revision' => time(),
        'permission' => 1,
        'size' => 200,
    ]);
    $dokuWiki->shouldReceive('getPage')->andReturn('====== Updated Page ======');
    $dokuWiki->shouldReceive('getPageHtml')->andReturn('<h1>Updated Page</h1>');
    $dokuWiki->shouldReceive('extractTableOfContents')->andReturn([]);
    $dokuWiki->shouldReceive('generateExcerpt')->andReturn('Updated Page');

    $service = new WikiContentService($dokuWiki);
    $stats = $service->syncUpdatedPages();

    expect($stats['processed'])->toBe(1);
    expect($stats['errors'])->toBe(0);
    expect(WikiSyncLog::count())->toBe(1);
    expect(WikiSyncLog::first()->type)->toBe('incremental');
});

it('uses last sync timestamp from database for incremental sync', function () {
    WikiSyncLog::create([
        'type' => 'full',
        'processed' => 50,
        'updated' => 50,
        'errors' => 0,
        'synced_at' => now()->subHour(),
    ]);

    $expectedTimestamp = WikiSyncLog::lastSyncTimestamp();

    $dokuWiki = mock(DokuWikiService::class);
    $dokuWiki->shouldReceive('getRecentChanges')
        ->with($expectedTimestamp)
        ->once()
        ->andReturn([]);

    $service = new WikiContentService($dokuWiki);
    $stats = $service->syncUpdatedPages();

    expect($stats['processed'])->toBe(0);
});

it('reports stats with last full sync time', function () {
    WikiSyncLog::create([
        'type' => 'full',
        'processed' => 100,
        'updated' => 95,
        'errors' => 5,
        'synced_at' => now()->subDay(),
    ]);

    WikiSyncLog::create([
        'type' => 'incremental',
        'processed' => 3,
        'updated' => 3,
        'errors' => 0,
        'synced_at' => now(),
    ]);

    $dokuWiki = mock(DokuWikiService::class);
    $service = new WikiContentService($dokuWiki);
    $stats = $service->getStats();

    expect($stats['last_sync_human'])->not->toBe('Never');
    expect($stats['last_full_sync_human'])->not->toBe('Never');
});

it('reports never when no syncs exist', function () {
    $dokuWiki = mock(DokuWikiService::class);
    $service = new WikiContentService($dokuWiki);
    $stats = $service->getStats();

    expect($stats['last_sync_human'])->toBe('Never');
    expect($stats['last_full_sync_human'])->toBe('Never');
});
