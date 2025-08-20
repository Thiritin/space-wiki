<?php

namespace App\Http\Controllers;

use App\Services\DokuWikiService;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class WikiController extends Controller
{
    public function __construct(
        private DokuWikiService $dokuwikiService
    ) {}

    public function index()
    {
        try {
            // Load all pages from database
            $pages = Page::select('page_id as id', 'title', 'namespace', 'last_modified', 'url')
                        ->orderBy('last_modified', 'desc')
                        ->get()
                        ->map(function ($page) {
                            return [
                                'id' => $page->id,
                                'title' => $page->title,
                                'namespace' => $page->namespace,
                                'size' => strlen($page->content ?? '') * 8, // Approximate size in bits
                                'lastModified' => $page->last_modified,
                            ];
                        })
                        ->toArray();
            
            // Get recent changes from database instead of API
            $recentChanges = Page::orderBy('last_modified', 'desc')
                ->take(10)
                ->get()
                ->map(function ($page) {
                    return [
                        'id' => $page->page_id,
                        'author' => 'DokuWiki',
                        'summary' => $page->excerpt ? substr($page->excerpt, 0, 100) : '',
                        'revision' => $page->last_modified,
                        'sizechange' => 0, // We don't track size changes
                    ];
                })
                ->toArray();
            
            // Get the main wiki index page content from database only
            $indexPageContent = '';
            $indexPageInfo = null;
            $extractedTitle = null;
            
            // Try to find a suitable index page from database
            $indexPages = ['start', 'index', 'main', 'home', 'wiki'];
            foreach ($indexPages as $indexPageId) {
                $indexPage = Page::findByPageId($indexPageId);
                if ($indexPage && $indexPage->html_content) {
                    $content = $indexPage->html_content;
                    $content = $this->transformDokuWikiLinks($content);
                    $extractedData = $this->extractH1Title($content);
                    $indexPageContent = $extractedData['content'];
                    $extractedTitle = $extractedData['title'];
                    $indexPageInfo = [
                        'lastModified' => $indexPage->last_modified,
                        'author' => 'DokuWiki',
                    ];
                    break;
                }
            }
            
            return Inertia::render('Wiki/Index', [
                'pages' => $pages,
                'recentChanges' => $recentChanges,
                'indexPageContent' => $indexPageContent,
                'indexPageInfo' => $indexPageInfo,
                'extractedTitle' => $extractedTitle,
                'breadcrumbs' => $this->generateBreadcrumbs('index'),
            ]);
        } catch (\Exception $e) {
            return Inertia::render('Wiki/Index', [
                'error' => 'Unable to connect to wiki. Please check configuration.',
                'pages' => [],
                'recentChanges' => [],
                'indexPageContent' => '',
                'indexPageInfo' => null,
                'breadcrumbs' => $this->generateBreadcrumbs('index'),
            ]);
        }
    }

    public function show(Request $request, string $page = null)
    {
        if (!$page) {
            $page = 'start'; // Default DokuWiki start page
        }

        try {
            // Load from database only - no DokuWiki API fallbacks
            $pageModel = Page::findByPageId($page);
            
            if (!$pageModel || !$pageModel->html_content) {
                // Page not found in database, try index page or generate sitemap
                throw new \Exception('Page not found in database');
            }
            
            // Load from database
            $pageInfo = [
                'lastModified' => $pageModel->last_modified,
                'author' => 'DokuWiki',
                'size' => strlen($pageModel->content ?? ''),
            ];
            $content = $pageModel->html_content;
            $content = $this->transformDokuWikiLinks($content);
            $extractedData = $this->extractH1Title($content);
            
            return Inertia::render('Wiki/Page', [
                'page' => $page,
                'pageInfo' => $pageInfo,
                'content' => $extractedData['content'],
                'extractedTitle' => $extractedData['title'],
                'subpages' => $pageModel?->subpages ?? [],
                'tableOfContents' => $pageModel?->table_of_contents ?? [],
                'breadcrumbs' => $this->generateBreadcrumbs($page, $extractedData['title']),
            ]);
        } catch (\Exception $e) {
            // Try fallback: if page doesn't exist, try {page}:index (database only)
            $indexPage = $page . ':index';
            $indexPageModel = Page::findByPageId($indexPage);
            
            if ($indexPageModel && $indexPageModel->html_content) {
                $pageInfo = [
                    'lastModified' => $indexPageModel->last_modified,
                    'author' => 'DokuWiki',
                    'size' => strlen($indexPageModel->content ?? ''),
                ];
                $content = $indexPageModel->html_content;
                $content = $this->transformDokuWikiLinks($content);
                $extractedData = $this->extractH1Title($content);
                
                return Inertia::render('Wiki/Page', [
                    'page' => $indexPage,
                    'pageInfo' => $pageInfo,
                    'content' => $extractedData['content'],
                    'extractedTitle' => $extractedData['title'],
                    'subpages' => $indexPageModel->subpages ?? [],
                    'tableOfContents' => $indexPageModel->table_of_contents ?? [],
                    'breadcrumbs' => $this->generateBreadcrumbs($indexPage, $extractedData['title']),
                ]);
            } else {
                // If neither page nor index page exist in database, generate a sitemap
                return $this->generateNamespaceSitemap($page);
            }
        }
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (empty($query)) {
            return Inertia::render('Wiki/Search', [
                'query' => '',
                'results' => [],
                'breadcrumbs' => $this->generateBreadcrumbs('search', 'Search'),
            ]);
        }

        try {
            $results = $this->dokuwikiService->searchPages($query);
            
            return Inertia::render('Wiki/Search', [
                'query' => $query,
                'results' => $results,
                'breadcrumbs' => $this->generateBreadcrumbs('search', 'Search'),
            ]);
        } catch (\Exception $e) {
            return Inertia::render('Wiki/Search', [
                'query' => $query,
                'error' => 'Search failed. Please try again.',
                'results' => [],
                'breadcrumbs' => $this->generateBreadcrumbs('search', 'Search'),
            ]);
        }
    }

    public function history(string $page)
    {
        // Load page info from database only
        $pageModel = Page::findByPageId($page);
        
        if (!$pageModel) {
            return Inertia::render('Wiki/History', [
                'page' => $page,
                'error' => 'Page not found in database.',
                'versions' => [],
                'pageInfo' => null,
                'breadcrumbs' => $this->generateBreadcrumbs($page, 'History'),
            ]);
        }
        
        // Since we don't store version history in the database,
        // show only the current version
        $versions = [
            [
                'timestamp' => $pageModel->last_modified,
                'author' => 'DokuWiki',
                'summary' => 'Current version',
                'size' => strlen($pageModel->content ?? ''),
            ]
        ];
        
        $pageInfo = [
            'lastModified' => $pageModel->last_modified,
            'author' => 'DokuWiki',
            'size' => strlen($pageModel->content ?? ''),
        ];
        
        return Inertia::render('Wiki/History', [
            'page' => $page,
            'pageInfo' => $pageInfo,
            'versions' => $versions,
            'breadcrumbs' => $this->generateBreadcrumbs($page, 'History'),
        ]);
    }

    public function attachments(string $namespace = '')
    {
        try {
            // Proxy to DokuWiki for attachments only
            $attachments = $this->dokuwikiService->getAttachments($namespace);
            
            return Inertia::render('Wiki/Attachments', [
                'namespace' => $namespace,
                'attachments' => $attachments,
                'breadcrumbs' => $this->generateBreadcrumbs('attachments', 'Attachments'),
            ]);
        } catch (\Exception $e) {
            return Inertia::render('Wiki/Attachments', [
                'namespace' => $namespace,
                'error' => 'Unable to load attachments.',
                'attachments' => [],
                'breadcrumbs' => $this->generateBreadcrumbs('attachments', 'Attachments'),
            ]);
        }
    }

    public function getTeamNamespaces()
    {
        try {
            $teamNamespaces = Cache::remember('wiki_team_namespaces', 3600, function () {
                // Extract unique team namespaces from database
                $teamNamespaces = [];
                $excludedNames = ['index', 'start', 'main', 'home', 'presse', 'press', 'news'];
                
                $teamPages = Page::where('page_id', 'like', 'team:%')->get();
                
                foreach ($teamPages as $page) {
                    $pageId = $page->page_id;
                    $parts = explode(':', $pageId);
                    
                    if (count($parts) >= 2) {
                        $teamName = $parts[1]; // e.g., 'it', 'accounting', 'security'
                        
                        // Skip excluded common names
                        if (in_array(strtolower($teamName), $excludedNames)) {
                            continue;
                        }
                        
                        // Only add if we haven't seen this team namespace before
                        if (!isset($teamNamespaces[$teamName])) {
                            $teamNamespaces[$teamName] = [
                                'name' => $teamName,
                                'displayName' => ucfirst(str_replace(['_', '-'], ' ', $teamName)),
                                'href' => route('wiki.show', ['page' => "team:{$teamName}"]),
                            ];
                        }
                    }
                }
                
                // Sort teams alphabetically by display name
                uasort($teamNamespaces, function($a, $b) {
                    return strcmp($a['displayName'], $b['displayName']);
                });
                
                return array_values($teamNamespaces);
            });
            
            return response()->json($teamNamespaces);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Unable to load team namespaces.',
                'teams' => [],
            ], 500);
        }
    }

    private function generateNamespaceSitemap(string $namespace): \Inertia\Response
    {
        try {
            // Load pages from database instead of DokuWiki API
            $namespacePages = Page::where('namespace', 'like', $namespace . '%')
                ->orWhere('page_id', 'like', $namespace . ':%')
                ->orderBy('title')
                ->get()
                ->map(function ($page) {
                    return [
                        'id' => $page->page_id,
                        'title' => $page->title,
                        'size' => strlen($page->content ?? '') * 8,
                        'lastModified' => $page->last_modified,
                        'href' => route('wiki.show', ['page' => $page->page_id]),
                        'excerpt' => $page->excerpt,
                    ];
                })
                ->toArray();
            
            // Generate sitemap content using subpages from database
            $parentPage = Page::findByPageId($namespace);
            $subpages = $parentPage?->subpages ?? [];
            
            $sitemapContent = $this->generateSitemapHtml($namespace, $namespacePages, $subpages);
            
            return Inertia::render('Wiki/Page', [
                'page' => $namespace,
                'pageInfo' => [
                    'id' => $namespace,
                    'lastModified' => time(),
                    'author' => 'System',
                    'size' => strlen($sitemapContent),
                ],
                'content' => $sitemapContent,
                'subpages' => $subpages,
                'tableOfContents' => [],
                'isSitemap' => true,
                'breadcrumbs' => $this->generateBreadcrumbs($namespace, 'Sitemap'),
            ]);
        } catch (\Exception $e) {
            return Inertia::render('Wiki/Page', [
                'page' => $namespace,
                'error' => 'Namespace not found and unable to generate sitemap.',
                'content' => '',
                'pageInfo' => null,
                'breadcrumbs' => $this->generateBreadcrumbs($namespace, 'Error'),
            ]);
        }
    }
    
    private function generateSitemapHtml(string $namespace, array $pages, array $subpages = []): string
    {
        $namespaceParts = explode(':', $namespace);
        $displayName = ucfirst(str_replace(['_', '-'], ' ', end($namespaceParts)));
        
        $html = "<div class='namespace-sitemap'>";
        $html .= "<h1>📁 {$displayName} Namespace</h1>";
        $html .= "<p class='text-gray-600 mb-6'>This namespace contains " . count($pages) . " page(s):</p>";
        
        if (empty($pages)) {
            $html .= "<div class='text-center py-8 text-gray-500'>";
            $html .= "<p>No pages found in this namespace.</p>";
            $html .= "</div>";
        } else {
            $html .= "<div class='grid gap-4'>";
            foreach ($pages as $page) {
                $relativeId = str_replace($namespace . ':', '', $page['id']);
                $sizeKb = round($page['size'] / 1024, 1);
                $lastModified = $page['lastModified'] ? date('M j, Y', $page['lastModified']) : 'Unknown';
                
                $html .= "<div class='border rounded-lg p-4 hover:bg-gray-50'>";
                $html .= "<h3><a href='{$page['href']}' class='text-blue-600 hover:text-blue-800 font-medium'>{$page['title']}</a></h3>";
                $html .= "<p class='text-sm text-gray-600'>Path: <code>{$relativeId}</code></p>";
                $html .= "<div class='flex justify-between text-xs text-gray-500 mt-2'>";
                $html .= "<span>Size: {$sizeKb} KB</span>";
                $html .= "<span>Modified: {$lastModified}</span>";
                $html .= "</div>";
                $html .= "</div>";
            }
            $html .= "</div>";
        }
        
        $html .= "</div>";
        
        return $html;
    }

    private function formatPageTitle(string $pageId): string
    {
        $parts = explode(':', $pageId);
        $lastPart = end($parts);
        
        return ucwords(str_replace(['_', '-'], ' ', $lastPart));
    }

    private function transformDokuWikiLinks(string $content): string
    {
        if (empty($content)) {
            return $content;
        }

        \Log::info('Before transformation', ['content_length' => strlen($content)]);

        // Transform DokuWiki links to Laravel routes
        // Pattern 1: href="/doku.php?id=page:name" or href="http://localhost/doku.php?id=page:name"
        $content = preg_replace_callback(
            '/href=["\'](?:https?:\/\/[^\/]+)?\/doku\.php\?id=([^"\'&]+)[^"\']*["\']/i',
            function ($matches) {
                $pageId = urldecode($matches[1]);
                return 'href="' . route('wiki.show', ['page' => $pageId]) . '"';
            },
            $content
        );

        // Pattern 2: href="?id=page:name"
        $content = preg_replace_callback(
            '/href=["\'](?:\?id=)([^"\'&]+)[^"\']*["\']/i',
            function ($matches) {
                $pageId = urldecode($matches[1]);
                return 'href="' . route('wiki.show', ['page' => $pageId]) . '"';
            },
            $content
        );

        // Pattern 3: Internal DokuWiki links that might be relative
        $content = preg_replace_callback(
            '/href=["\'](?:\.\/)?doku\.php\?id=([^"\'&]+)[^"\']*["\']/i',
            function ($matches) {
                $pageId = urldecode($matches[1]);
                return 'href="' . route('wiki.show', ['page' => $pageId]) . '"';
            },
            $content
        );

        // Pattern 4: Action links with do parameter (edit, show, etc.)
        $content = preg_replace_callback(
            '/href=["\'](?:https?:\/\/[^\/]+)?\/doku\.php\?(?:[^"\']*&)?do=([^&"\']+)(?:[^"\']*&)?id=([^"\'&]+)[^"\']*["\']/i',
            function ($matches) {
                $action = $matches[1];
                $pageId = urldecode($matches[2]);
                
                // For now, just redirect to the page view
                // You could extend this to handle edit links differently
                return 'href="' . route('wiki.show', ['page' => $pageId]) . '"';
            },
            $content
        );

        // Transform image URLs - Simply replace the path, keep all parameters
        $originalContent = $content;
        
        // Pattern 1: Full URLs with domain
        $content = preg_replace(
            '/src=["\']https?:\/\/[^\/]+\/lib\/exe\/fetch\.php\?/i',
            'src="/wiki/fetch?',
            $content
        );
        
        // Pattern 2: Relative URLs
        $content = preg_replace(
            '/src=["\']\/lib\/exe\/fetch\.php\?/i',
            'src="/wiki/fetch?',
            $content
        );
        
        if ($originalContent !== $content) {
            \Log::info('Image URLs were transformed using simple path replacement');
        } else {
            \Log::info('No image URLs were transformed - checking for fetch.php in content', ['has_fetch' => strpos($content, 'fetch.php') !== false]);
        }

        // Pattern 2: img tags with DokuWiki image URLs
        $content = preg_replace_callback(
            '/src=["\'](?:https?:\/\/[^\/]+)?\/(?:_media\/|media\/)([^"\']+)["\']/i',
            function ($matches) {
                $imagePath = urldecode($matches[1]);
                return 'src="' . route('wiki.image', ['imagePath' => $imagePath]) . '"';
            },
            $content
        );

        // Pattern 3: Background images and other image references
        $content = preg_replace_callback(
            '/url\(["\']?(?:https?:\/\/[^\/]+)?\/lib\/exe\/fetch\.php\?([^"\'&)]+)["\']?\)/i',
            function ($matches) {
                parse_str($matches[1], $params);
                if (isset($params['media'])) {
                    $imagePath = $params['media'];
                    // Pass through relevant parameters
                    $proxyParams = [];
                    if (isset($params['w'])) $proxyParams['w'] = $params['w'];
                    if (isset($params['h'])) $proxyParams['h'] = $params['h'];
                    
                    $url = route('wiki.image', ['imagePath' => $imagePath]);
                    if (!empty($proxyParams)) {
                        $url .= '?' . http_build_query($proxyParams);
                    }
                    return 'url("' . $url . '")';
                }
                return $matches[0]; // Return original if no media parameter
            },
            $content
        );

        return $content;
    }

    public function getSubpages(Request $request)
    {
        $page = $request->get('page');
        
        if (!$page) {
            return response()->json([]);
        }
        
        try {
            // If the current page ends with :index, use the namespace without :index
            $namespace = str_ends_with($page, ':index') ? substr($page, 0, -6) : $page;
            
            // Use getNamespacePages with depth 2 to get direct children and one level deeper
            $allPages = $this->dokuwikiService->getNamespacePages($namespace, 2);
            $subpages = [];
            $subfolders = [];
            
            // Find all pages that are children of this namespace
            foreach ($allPages as $pageData) {
                $pageId = $pageData['id'] ?? '';
                
                // Check if page starts with our namespace followed by a colon
                if (strpos($pageId, $namespace . ':') === 0) {
                    // Get the part after our namespace
                    $remainder = substr($pageId, strlen($namespace) + 1);
                    
                    // Skip if it's the current page
                    if ($pageId === $page || $remainder === 'index') {
                        continue;
                    }
                    
                    // Check if this is a direct child (no more colons) - regular page
                    if (strpos($remainder, ':') === false) {
                        $subpages[] = [
                            'id' => $pageId,
                            'title' => $this->formatPageTitle($pageId),
                            'href' => route('wiki.show', ['page' => $pageId]),
                            'size' => $pageData['size'] ?? 0,
                            'lastModified' => $pageData['lastModified'] ?? null,
                            'type' => 'page'
                        ];
                    } else {
                        // This is a deeper nested page - check if it's a subfolder index
                        $parts = explode(':', $remainder);
                        if (count($parts) === 2 && $parts[1] === 'index') {
                            // This is a subfolder with an index page
                            $subfolderName = $parts[0];
                            $subfolderNamespace = $namespace . ':' . $subfolderName;
                            
                            // Only add if we haven't seen this subfolder before
                            if (!isset($subfolders[$subfolderName])) {
                                $subfolders[$subfolderName] = [
                                    'id' => $subfolderNamespace . ':index',
                                    'title' => $this->formatPageTitle($subfolderNamespace),
                                    'href' => route('wiki.show', ['page' => $subfolderNamespace . ':index']),
                                    'size' => $pageData['size'] ?? 0,
                                    'lastModified' => $pageData['lastModified'] ?? null,
                                    'type' => 'folder'
                                ];
                            }
                        }
                    }
                }
            }
            
            // Combine subpages and subfolders
            $allSubitems = array_merge($subpages, array_values($subfolders));
            
            // Sort by type (folders first) then by title
            usort($allSubitems, function($a, $b) {
                // Folders first
                if ($a['type'] !== $b['type']) {
                    return $a['type'] === 'folder' ? -1 : 1;
                }
                // Then alphabetically by title
                return strcmp($a['title'], $b['title']);
            });
            
            return response()->json($allSubitems);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    private function extractH1Title(string $content): array
    {
        // Pattern to match the first h1 tag and capture its content
        $pattern = '/<h1[^>]*>(.*?)<\/h1>/i';
        
        if (preg_match($pattern, $content, $matches)) {
            // Extract the title text and strip any HTML tags
            $title = strip_tags($matches[1]);
            
            // Remove the first h1 from content
            $contentWithoutH1 = preg_replace($pattern, '', $content, 1);
            
            return [
                'title' => trim($title),
                'content' => $contentWithoutH1
            ];
        }
        
        // No h1 found, return original content with null title
        return [
            'title' => null,
            'content' => $content
        ];
    }

    private function generateBreadcrumbs(string $pageId, ?string $pageTitle = null): array
    {
        $breadcrumbs = [];
        
        // Always start with Wiki home
        $breadcrumbs[] = [
            'title' => 'Wiki',
            'href' => route('wiki.index'),
        ];

        if ($pageId === 'start' || $pageId === 'index') {
            // For the main wiki page, just return Wiki home
            return $breadcrumbs;
        }

        // Split the page ID into parts
        $parts = explode(':', $pageId);
        $currentPath = '';

        foreach ($parts as $index => $part) {
            $currentPath = $currentPath ? $currentPath . ':' . $part : $part;
            $isLast = $index === count($parts) - 1;
            
            // For the last part, use the page title if available
            if ($isLast && $pageTitle) {
                $title = $pageTitle;
            } else {
                // Format the part name
                $title = ucfirst(str_replace(['_', '-'], ' ', $part));
            }

            // Only add href for non-last items (breadcrumbs don't link to current page)
            $breadcrumbs[] = [
                'title' => $title,
                'href' => $isLast ? '' : route('wiki.show', ['page' => $currentPath]),
            ];
        }

        return $breadcrumbs;
    }

}