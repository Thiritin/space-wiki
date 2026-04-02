<?php

use App\Models\WikiSyncLog;

it('stores a sync log entry', function () {
    WikiSyncLog::create([
        'type' => 'full',
        'processed' => 10,
        'updated' => 8,
        'errors' => 2,
        'synced_at' => now(),
    ]);

    expect(WikiSyncLog::count())->toBe(1);
    expect(WikiSyncLog::first()->type)->toBe('full');
    expect(WikiSyncLog::first()->processed)->toBe(10);
});

it('returns zero timestamp when no syncs exist', function () {
    expect(WikiSyncLog::lastSyncTimestamp())->toBe(0);
});

it('returns the latest sync timestamp', function () {
    WikiSyncLog::create([
        'type' => 'incremental',
        'processed' => 5,
        'updated' => 3,
        'errors' => 0,
        'synced_at' => now()->subHour(),
    ]);

    WikiSyncLog::create([
        'type' => 'incremental',
        'processed' => 3,
        'updated' => 2,
        'errors' => 0,
        'synced_at' => now(),
    ]);

    $latest = WikiSyncLog::query()->latest('synced_at')->first();
    expect(WikiSyncLog::lastSyncTimestamp())->toBe($latest->synced_at->timestamp);
});

it('returns null when no full sync exists', function () {
    WikiSyncLog::create([
        'type' => 'incremental',
        'processed' => 5,
        'updated' => 3,
        'errors' => 0,
        'synced_at' => now(),
    ]);

    expect(WikiSyncLog::lastFullSync())->toBeNull();
});

it('returns the latest full sync', function () {
    WikiSyncLog::create([
        'type' => 'full',
        'processed' => 100,
        'updated' => 90,
        'errors' => 0,
        'synced_at' => now()->subDay(),
    ]);

    WikiSyncLog::create([
        'type' => 'incremental',
        'processed' => 5,
        'updated' => 3,
        'errors' => 0,
        'synced_at' => now(),
    ]);

    $lastFull = WikiSyncLog::lastFullSync();
    expect($lastFull)->not->toBeNull();
    expect($lastFull->type)->toBe('full');
    expect($lastFull->processed)->toBe(100);
});
