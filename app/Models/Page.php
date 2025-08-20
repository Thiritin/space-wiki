<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Carbon\Carbon;

class Page extends Model
{
    use Searchable;

    protected $table = 'pages';

    protected $fillable = [
        'page_id',
        'title',
        'content',
        'html_content',
        'subpages',
        'table_of_contents',
        'excerpt',
        'namespace',
        'depth',
        'last_modified',
        'url',
    ];

    protected $casts = [
        'last_modified' => 'integer',
        'subpages' => 'array',
        'table_of_contents' => 'array',
        'depth' => 'integer',
    ];

    public function searchableAs(): string
    {
        return 'pages';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->page_id,
            'title' => $this->title,
            'content' => $this->content,
            'namespace' => $this->namespace,
            'last_modified' => $this->last_modified,
            'url' => $this->url,
        ];
    }

    public function getScoutKey(): mixed
    {
        return $this->page_id;
    }

    public function getScoutKeyName(): string
    {
        return 'page_id';
    }

    public function getLastModifiedHumanAttribute(): string
    {
        return $this->last_modified > 0 
            ? Carbon::createFromTimestamp($this->last_modified)->diffForHumans()
            : 'Unknown';
    }

    public static function findByPageId(string $pageId): ?self
    {
        return static::where('page_id', $pageId)->first();
    }

    public static function createFromDokuWiki(
        string $pageId, 
        array $pageInfo, 
        string $content, 
        string $htmlContent = '',
        array $subpages = [],
        array $tableOfContents = [],
        string $excerpt = ''
    ): self {
        $title = static::extractTitle($pageId, $content);
        $namespace = static::extractNamespace($pageId);
        $cleanContent = static::cleanContent($content);
        $depth = substr_count($pageId, ':');
        
        return static::updateOrCreate(
            ['page_id' => $pageId],
            [
                'title' => $title,
                'content' => $cleanContent,
                'html_content' => $htmlContent,
                'subpages' => $subpages,
                'table_of_contents' => $tableOfContents,
                'excerpt' => $excerpt,
                'namespace' => $namespace,
                'depth' => $depth,
                'last_modified' => $pageInfo['lastModified'] ?? time(),
                'url' => "/wiki/{$pageId}",
            ]
        );
    }

    private static function extractTitle(string $pageId, string $content): string
    {
        if (preg_match('/^======\s*(.+?)\s*======/m', $content, $matches)) {
            return trim($matches[1]);
        }
        
        if (preg_match('/^=====\s*(.+?)\s*=====/m', $content, $matches)) {
            return trim($matches[1]);
        }
        
        $parts = explode(':', $pageId);
        return ucfirst(str_replace('_', ' ', end($parts)));
    }

    private static function extractNamespace(string $pageId): string
    {
        $parts = explode(':', $pageId);
        array_pop($parts);
        return implode(':', $parts) ?: 'root';
    }

    private static function cleanContent(string $content): string
    {
        $content = preg_replace('/======\s*(.+?)\s*======/', '$1', $content);
        $content = preg_replace('/=====\s*(.+?)\s*=====/', '$1', $content);
        $content = preg_replace('/====\s*(.+?)\s*====/', '$1', $content);
        $content = preg_replace('/===\s*(.+?)\s*===/', '$1', $content);
        $content = preg_replace('/==\s*(.+?)\s*==/', '$1', $content);
        
        $content = preg_replace('/\[\[([^\]]+)\]\]/', '$1', $content);
        $content = preg_replace('/\{\{([^}]+)\}\}/', '', $content);
        $content = preg_replace('/^\s*[\*\-]\s*/m', '', $content);
        $content = preg_replace('/^\s*\d+\.\s*/m', '', $content);
        
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        return trim($content);
    }
}