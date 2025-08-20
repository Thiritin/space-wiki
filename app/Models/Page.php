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
        'table_of_contents',
        'excerpt',
        'namespace',
        'depth',
        'last_modified',
        'revision',
        'permission',
        'size_bytes',
    ];

    protected $casts = [
        'last_modified' => 'integer',
        'revision' => 'integer',
        'permission' => 'integer',
        'size_bytes' => 'integer',
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

    public function getRevisionHumanAttribute(): string
    {
        return $this->revision ? Carbon::createFromTimestamp($this->revision)->format('M j, Y g:i A') : 'Unknown';
    }

    public function getSizeBytesHumanAttribute(): string
    {
        if (!$this->size_bytes) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->size_bytes;
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 1) . ' ' . $units[$i];
    }

    public function getPermissionLevelAttribute(): string
    {
        if ($this->permission === null) {
            return 'Unknown';
        }
        
        // DokuWiki permission levels
        $levels = [
            0 => 'None',
            1 => 'Read',
            2 => 'Edit', 
            4 => 'Create',
            8 => 'Upload',
            16 => 'Delete',
        ];
        
        return $levels[$this->permission] ?? "Level {$this->permission}";
    }

    public function getUrlAttribute(): string
    {
        return "/wiki/{$this->page_id}";
    }

    /**
     * Get subpages of this page dynamically (2 levels deep with indentation)
     * Returns empty array if there are more than 50 entries to avoid performance issues
     */
    public function getSubpages(): array
    {
        // Handle index pages specially - show siblings instead of children
        if (str_ends_with($this->page_id, ':index')) {
            // For team:it:index, show subpages of team:it
            $parentNamespace = substr($this->page_id, 0, -6); // Remove ':index'
            $basePattern = $parentNamespace . ':%';
            $baseDepth = $this->depth - 1 + 1; // Parent depth + 1
        } else {
            // Normal case - show direct children
            $basePattern = $this->page_id . ':%';
            $baseDepth = $this->depth + 1;
        }
        
        // First check the total count to avoid loading too many entries
        $totalCount = static::where('page_id', 'like', $basePattern)
            ->where('depth', '>=', $baseDepth)
            ->where('depth', '<=', $baseDepth + 1) // 2 levels deep
            ->where('page_id', '!=', $this->page_id) // Exclude self
            ->count();
        
        // If there are more than 50 entries, don't render subpages for performance
        if ($totalCount > 50) {
            return [
                '_meta' => [
                    'hidden_due_to_limit' => true,
                    'total_count' => $totalCount
                ]
            ];
        }
        
        // Get pages up to 2 levels deep
        $allSubpages = static::where('page_id', 'like', $basePattern)
            ->where('depth', '>=', $baseDepth)
            ->where('depth', '<=', $baseDepth + 1) // 2 levels deep
            ->where('page_id', '!=', $this->page_id) // Exclude self
            ->orderBy('page_id') // Order by full path to maintain hierarchy
            ->get();
        
        $result = [];
        $processedNamespaces = [];
        
        foreach ($allSubpages as $page) {
            $depth = $page->depth;
            $isLevel1 = $depth === $baseDepth;
            $isLevel2 = $depth === $baseDepth + 1;
            
            if ($isLevel1) {
                // Level 1: Direct children
                // Use page title if available and not generic, otherwise use formatted namespace
                $displayTitle = $page->title;
                if (!$displayTitle || $this->isGenericTitle($displayTitle)) {
                    $displayTitle = $this->formatNamespaceTitle($page->page_id);
                }
                
                $result[] = [
                    'id' => $page->page_id,
                    'title' => $displayTitle,
                    'url' => $page->url,
                    'size' => $page->size_bytes ?? strlen($page->content ?? '') * 8,
                    'lastModified' => $page->last_modified,
                    'type' => 'page',
                    'level' => 1,
                    'isFolder' => $this->hasSubpages($page->page_id, $baseDepth + 1)
                ];
                
                // Track this namespace for level 2 processing
                $processedNamespaces[$page->page_id] = true;
            } elseif ($isLevel2) {
                // Level 2: Check if parent namespace exists or create folder entry
                $parentPageId = $this->getParentPageId($page->page_id);
                
                // If parent doesn't exist as a page, create a folder entry
                if (!isset($processedNamespaces[$parentPageId])) {
                    $parentPage = static::findByPageId($parentPageId . ':index') ?: static::findByPageId($parentPageId);
                    
                    // Use page title if available and not generic, otherwise use formatted namespace
                    if ($parentPage && $parentPage->title && !$this->isGenericTitle($parentPage->title)) {
                        $folderTitle = $parentPage->title;
                    } else {
                        $folderTitle = $this->formatNamespaceTitle($parentPageId);
                    }
                    
                    $result[] = [
                        'id' => $parentPageId,
                        'title' => $folderTitle,
                        'url' => "/wiki/{$parentPageId}",
                        'size' => 0,
                        'lastModified' => 0,
                        'type' => 'folder',
                        'level' => 1,
                        'isFolder' => true
                    ];
                    
                    $processedNamespaces[$parentPageId] = true;
                }
                
                // Add the level 2 page as indented
                // Use page title if available and not generic, otherwise use formatted namespace
                $displayTitle = $page->title;
                if (!$displayTitle || $this->isGenericTitle($displayTitle)) {
                    $displayTitle = $this->formatNamespaceTitle($page->page_id);
                }
                
                $result[] = [
                    'id' => $page->page_id,
                    'title' => $displayTitle,
                    'url' => $page->url,
                    'size' => $page->size_bytes ?? strlen($page->content ?? '') * 8,
                    'lastModified' => $page->last_modified,
                    'type' => 'page',
                    'level' => 2,
                    'isFolder' => false
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Check if a page has subpages at a specific depth
     */
    private function hasSubpages(string $pageId, int $targetDepth): bool
    {
        return static::where('page_id', 'like', $pageId . ':%')
            ->where('depth', $targetDepth)
            ->exists();
    }
    
    /**
     * Get parent page ID from a page ID
     */
    private function getParentPageId(string $pageId): string
    {
        $parts = explode(':', $pageId);
        array_pop($parts); // Remove last part
        return implode(':', $parts);
    }
    
    /**
     * Format namespace title from page ID
     */
    private function formatNamespaceTitle(string $pageId): string
    {
        $parts = explode(':', $pageId);
        $lastPart = end($parts);
        return ucfirst(str_replace(['_', '-'], ' ', $lastPart));
    }
    
    /**
     * Check if a title is generic and should not be used for folder names
     */
    private function isGenericTitle(string $title): bool
    {
        $genericTitles = ['index', 'main', 'home', 'start', 'default'];
        return in_array(strtolower(trim($title)), $genericTitles);
    }

    /**
     * Get subpages attribute dynamically
     */
    public function getSubpagesAttribute(): array
    {
        return $this->getSubpages();
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
                'table_of_contents' => $tableOfContents,
                'excerpt' => $excerpt,
                'namespace' => $namespace,
                'depth' => $depth,
                'last_modified' => $pageInfo['lastModified'] ?? time(),
                'revision' => $pageInfo['revision'] ?? null,
                'permission' => $pageInfo['permission'] ?? null,
                'size_bytes' => $pageInfo['size'] ?? null,
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