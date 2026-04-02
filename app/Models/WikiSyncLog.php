<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WikiSyncLog extends Model
{
    public $timestamps = false;

    protected $table = 'wiki_sync_log';

    protected $fillable = [
        'type',
        'processed',
        'updated',
        'errors',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }

    public static function lastSyncTimestamp(): int
    {
        $lastSync = static::query()->latest('synced_at')->first();

        return $lastSync ? $lastSync->synced_at->timestamp : 0;
    }

    public static function lastFullSync(): ?self
    {
        return static::query()
            ->where('type', 'full')
            ->latest('synced_at')
            ->first();
    }
}
