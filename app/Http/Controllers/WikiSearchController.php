<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;

class WikiSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 10), 50);

        if (empty($query)) {
            return response()->json([
                'hits' => [],
                'query' => $query,
                'processing_time_ms' => 0,
                'nb_hits' => 0,
            ]);
        }

        $startTime = microtime(true);

        $results = Page::search($query)
            ->take($limit)
            ->get()
            ->map(function ($page) use ($query) {
                return [
                    'id' => $page->page_id,
                    'title' => $page->title,
                    'content' => $this->truncateContent($page->content, 150),
                    'namespace' => $page->namespace,
                    'url' => $page->url,
                    'last_modified' => $page->last_modified,
                    'last_modified_human' => $page->last_modified_human,
                    '_highlightResult' => $this->generateHighlights($page, $query),
                ];
            });

        $processingTime = round((microtime(true) - $startTime) * 1000);

        return response()->json([
            'hits' => $results,
            'query' => $query,
            'processing_time_ms' => $processingTime,
            'nb_hits' => $results->count(),
        ]);
    }

    public function suggest(Request $request)
    {
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 5), 20);

        if (strlen($query) < 2) {
            return response()->json([
                'suggestions' => [],
                'query' => $query,
            ]);
        }

        $suggestions = Page::search($query)
            ->take($limit)
            ->get()
            ->map(function ($page) {
                return [
                    'title' => $page->title,
                    'namespace' => $page->namespace,
                    'url' => $page->url,
                ];
            });

        return response()->json([
            'suggestions' => $suggestions,
            'query' => $query,
        ]);
    }

    private function truncateContent(string $content, int $length): string
    {
        if (strlen($content) <= $length) {
            return $content;
        }

        return substr($content, 0, $length) . '...';
    }

    private function generateContentExcerpt(string $content, array $queryWords, int $maxLength = 200): string
    {
        $content = strip_tags($content);
        $lowerContent = strtolower($content);
        $bestMatch = null;
        $bestScore = 0;

        foreach ($queryWords as $word) {
            if (strlen($word) < 2) continue;
            
            $pos = strpos($lowerContent, strtolower($word));
            if ($pos !== false) {
                $start = max(0, $pos - 80);
                $end = min(strlen($content), $pos + strlen($word) + 80);
                $excerpt = substr($content, $start, $end - $start);
                
                $score = substr_count(strtolower($excerpt), strtolower($word));
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = [
                        'excerpt' => $excerpt,
                        'needsEllipsis' => $start > 0 || $end < strlen($content)
                    ];
                }
            }
        }

        if ($bestMatch) {
            $excerpt = $bestMatch['excerpt'];
            if ($bestMatch['needsEllipsis']) {
                $excerpt = '...' . trim($excerpt) . '...';
            }
            return $excerpt;
        }

        return $this->truncateContent($content, $maxLength);
    }

    private function generateHighlights($page, $query): array
    {
        $queryWords = array_filter(explode(' ', strtolower(trim($query))), function($word) {
            return strlen($word) > 1;
        });
        
        return [
            'title' => [
                'value' => $this->highlightText($page->title, $queryWords),
                'matchLevel' => $this->getMatchLevel($page->title, $queryWords),
            ],
            'content' => [
                'value' => $this->highlightText($this->generateContentExcerpt($page->content, $queryWords), $queryWords),
                'matchLevel' => $this->getMatchLevel($page->content, $queryWords),
            ],
        ];
    }

    private function highlightText(string $text, array $queryWords): string
    {
        foreach ($queryWords as $word) {
            if (strlen($word) > 1) {
                $text = preg_replace(
                    '/(' . preg_quote($word, '/') . ')/i',
                    '<mark>$1</mark>',
                    $text
                );
            }
        }
        
        return $text;
    }

    private function getMatchLevel(string $text, array $queryWords): string
    {
        $lowerText = strtolower($text);
        $matches = 0;
        
        foreach ($queryWords as $word) {
            if (strlen($word) > 1 && strpos($lowerText, strtolower($word)) !== false) {
                $matches++;
            }
        }
        
        $matchRatio = $matches / count($queryWords);
        
        if ($matchRatio >= 0.8) {
            return 'full';
        } elseif ($matchRatio >= 0.4) {
            return 'partial';
        } else {
            return 'none';
        }
    }
}