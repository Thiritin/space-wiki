<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DokuWikiService
{
    private string $uri;
    private string $username;
    private string $password;
    private string $jsonrpcEndpoint;

    public function __construct()
    {
        $this->uri = config('wiki.dokuwiki.uri');
        $this->username = config('wiki.dokuwiki.username');
        $this->password = config('wiki.dokuwiki.password');
        $this->jsonrpcEndpoint = config('wiki.dokuwiki.jsonrpc_endpoint');
    }

    public function makeRequest(string $method, array $params = []): mixed
    {
        $url = rtrim($this->uri, '/') . $this->jsonrpcEndpoint;
        
        $payload = [
            'jsonrpc' => '2.0',
            'id' => uniqid(),
            'method' => $method,
            'params' => $params,
        ];

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::error('DokuWiki API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'method' => $method,
                ]);
                throw new \Exception("DokuWiki API request failed with status {$response->status()}");
            }

            $result = $response->json();

            if (isset($result['error'])) {
                Log::error('DokuWiki API returned error', [
                    'error' => $result['error'],
                    'method' => $method,
                ]);
                throw new \Exception("DokuWiki API error: {$result['error']['message']}");
            }

            return $result['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('DokuWiki API exception', [
                'message' => $e->getMessage(),
                'method' => $method,
            ]);
            throw $e;
        }
    }

    public function getPageInfo(string $page): array
    {
        $result = $this->makeRequest('core.getPageInfo', [$page]);
        return is_array($result) ? $result : [];
    }

    public function getPage(string $page): string
    {
        $result = $this->makeRequest('core.getPage', [$page]);
        return is_string($result) ? $result : '';
    }

    public function getPageHtml(string $page): string
    {
        // Try different possible method names for getting HTML
        $methods = ['core.getPageHTML', 'wiki.getPageHTML', 'core.renderPage'];
        
        foreach ($methods as $method) {
            try {
                $result = $this->makeRequest($method, [$page]);
                if (is_string($result) && !empty($result)) {
                    return $result;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Fallback: use the existing renderPage method
        return $this->renderPage($page);
    }

    public function getAllPages(): array
    {
        // Request all pages by setting a high limit and including all namespaces
        $params = [
            'depth' => 0, // Get all depths/levels
            'limit' => 0  // 0 usually means no limit in DokuWiki
        ];
        $result = $this->makeRequest('core.listPages', $params);
        return is_array($result) ? $result : [];
    }

    public function getNamespacePages(string $namespace = '', int $depth = 1): array
    {
        $params = [];
        if (!empty($namespace)) {
            $params['namespace'] = $namespace;
        }
        if ($depth !== 1) {
            $params['depth'] = $depth;
        }
        
        \Log::info('getNamespacePages called', ['namespace' => $namespace, 'depth' => $depth, 'params' => $params]);
        
        // Pass params as object (named parameters) instead of array
        $result = $this->makeRequest('core.listPages', $params);
        
        \Log::info('getNamespacePages result', ['result' => $result, 'is_array' => is_array($result)]);
        
        return is_array($result) ? $result : [];
    }

    public function getPageVersions(string $page, int $offset = 0): array
    {
        $result = $this->makeRequest('core.getPageHistory', [$page, $offset]);
        return is_array($result) ? $result : [];
    }

    public function searchPages(string $query): array
    {
        $result = $this->makeRequest('core.searchPages', [$query]);
        return is_array($result) ? $result : [];
    }

    public function getSubpages(string $pageId): array
    {
        // Use database instead of API calls for better performance
        $depth = substr_count($pageId, ':');
        
        // Find direct children: pages that start with "pageId:" and are exactly one level deeper
        $subpages = \App\Models\Page::where('page_id', 'like', $pageId . ':%')
            ->where('depth', $depth + 1)
            ->orderBy('title')
            ->get()
            ->map(function ($page) {
                return [
                    'id' => $page->page_id,
                    'title' => $page->title,
                    'url' => "/wiki/{$page->page_id}",
                    'size' => strlen($page->content ?? '') * 8,
                    'lastModified' => $page->last_modified,
                ];
            })
            ->toArray();
        
        return $subpages;
    }

    public function extractTableOfContents(string $htmlContent): array
    {
        $toc = [];
        $dom = new \DOMDocument();
        
        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        $headings = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        
        foreach ($headings as $heading) {
            $level = (int) substr($heading->tagName, 1); // Extract number from h1, h2, etc.
            $text = trim($heading->textContent);
            $id = $this->generateHeadingId($text);
            
            if (!empty($text)) {
                $toc[] = [
                    'level' => $level,
                    'title' => $text,
                    'id' => $id,
                    'url' => "#{$id}",
                ];
            }
        }
        
        return $toc;
    }

    public function generateExcerpt(string $content, int $maxLength = 200): string
    {
        // Strip HTML tags and clean up content
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        
        // Find the last complete word within the limit
        $excerpt = substr($text, 0, $maxLength);
        $lastSpace = strrpos($excerpt, ' ');
        
        if ($lastSpace !== false) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }
        
        return $excerpt . '...';
    }

    private function extractNamespace(string $pageId): string
    {
        $parts = explode(':', $pageId);
        array_pop($parts); // Remove the page name
        return implode(':', $parts);
    }

    private function extractTitle(string $pageId): string
    {
        $parts = explode(':', $pageId);
        $title = end($parts);
        return ucfirst(str_replace('_', ' ', $title));
    }

    private function isSubpage(string $parentPageId, string $childPageId): bool
    {
        // Check if childPageId is a direct child of parentPageId
        $parentNamespace = $this->extractNamespace($parentPageId);
        $childNamespace = $this->extractNamespace($childPageId);
        
        // If parent is in root, child should be one level deep
        if (empty($parentNamespace)) {
            return substr_count($childPageId, ':') === 1;
        }
        
        // Child should start with parent namespace and be one level deeper
        return strpos($childPageId, $parentNamespace . ':') === 0 && 
               substr_count($childPageId, ':') === substr_count($parentNamespace, ':') + 1;
    }

    private function generateHeadingId(string $text): string
    {
        $id = strtolower($text);
        $id = preg_replace('/[^a-z0-9\s-]/', '', $id);
        $id = preg_replace('/[\s-]+/', '-', $id);
        $id = trim($id, '-');
        
        return $id ?: 'heading';
    }

    public function listNamespaces(string $namespace = ''): array
    {
        try {
            // Try to get attachments for the namespace - this will show us subdirectories
            $result = $this->makeRequest('core.listAttachments', [$namespace]);
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            // If that fails, try to derive namespaces from pages
            return $this->deriveNamespacesFromPages($namespace);
        }
    }

    private function deriveNamespacesFromPages(string $parentNamespace = ''): array
    {
        $allPages = $this->getAllPages();
        $namespaces = [];
        
        // Common page names that are not team namespaces
        $excludedNames = ['index', 'start', 'main', 'home', 'presse', 'press', 'news'];
        
        foreach ($allPages as $page) {
            $pageId = $page['id'] ?? '';
            
            // If we're looking for a specific parent namespace
            if (!empty($parentNamespace)) {
                if (strpos($pageId, $parentNamespace . ':') !== 0) {
                    continue;
                }
                // Remove the parent namespace prefix
                $remainingPath = substr($pageId, strlen($parentNamespace) + 1);
            } else {
                $remainingPath = $pageId;
            }
            
            // Get the first level namespace
            $parts = explode(':', $remainingPath);
            if (count($parts) > 1) {
                $namespace = $parts[0];
                
                // Skip excluded common names
                if (in_array(strtolower($namespace), $excludedNames)) {
                    continue;
                }
                
                $fullNamespace = empty($parentNamespace) ? $namespace : $parentNamespace . ':' . $namespace;
                
                if (!isset($namespaces[$namespace])) {
                    $namespaces[$namespace] = [
                        'id' => $fullNamespace,
                        'name' => $namespace,
                        'isdir' => true,
                    ];
                }
            }
        }
        
        return array_values($namespaces);
    }

    public function getRecentChanges(int $timestamp = 0): array
    {
        $result = $this->makeRequest('core.getRecentPageChanges', [$timestamp]);
        return is_array($result) ? $result : [];
    }

    public function renderPage(string $page): string
    {
        $result = $this->makeRequest('core.getPageHTML', [$page]);
        return is_string($result) ? $result : '';
    }

    public function getBacklinks(string $page): array
    {
        $result = $this->makeRequest('core.getPageBackLinks', [$page]);
        return is_array($result) ? $result : [];
    }

    public function getAttachments(string $namespace = ''): array
    {
        $result = $this->makeRequest('core.getAttachments', [$namespace]);
        return is_array($result) ? $result : [];
    }

    public function getAttachmentInfo(string $attachment): array
    {
        $result = $this->makeRequest('core.getAttachmentInfo', [$attachment]);
        return is_array($result) ? $result : [];
    }
}