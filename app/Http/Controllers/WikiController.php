<?php

namespace App\Http\Controllers;

use App\Services\DokuWikiService;
use App\Services\EurofurenceService;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class WikiController extends Controller
{
    public function __construct(
        private DokuWikiService $dokuwikiService,
        private EurofurenceService $eurofurenceService
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
                        'revision' => $page->revision ?? $page->last_modified,
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
                        'revision' => $indexPage->revision,
                        'size' => $indexPage->size_bytes,
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

    public function show(Request $request, ?string $page = null)
    {
        if (!$page) {
            $page = 'start'; // Default DokuWiki start page
        }

        // Check if this is the current EF page
        $currentEF = $this->eurofurenceService->getCurrentEF();
        if ($page === $currentEF) {
            return $this->showCurrentEurofurence($page);
        }

        try {
            // Load from database only - no DokuWiki API fallbacks
            $pageModel = Page::findByPageId($page);
            
            if (!$pageModel || !$pageModel->html_content) {
                // Page not found in database, try index page or generate sitemap
                throw new \Exception('Page not found in database');
            }
            
            // Load from database with enhanced metadata
            $pageInfo = [
                'lastModified' => $pageModel->last_modified,
                'author' => 'DokuWiki', // Author is always empty from DokuWiki
                'size' => $pageModel->size_bytes ?? strlen($pageModel->content ?? ''),
                'revision' => $pageModel->revision,
                'permission' => $pageModel->permission,
            ];
            $content = $pageModel->html_content;
            $content = $this->transformDokuWikiLinks($content);
            $extractedData = $this->extractH1Title($content);
            
            return Inertia::render('Wiki/Page', [
                'page' => $page,
                'pageInfo' => $pageInfo,
                'content' => $extractedData['content'],
                'extractedTitle' => $extractedData['title'],
                'subpages' => $pageModel->subpages,
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
                    'size' => $indexPageModel->size_bytes ?? strlen($indexPageModel->content ?? ''),
                    'revision' => $indexPageModel->revision,
                    'permission' => $indexPageModel->permission,
                ];
                $content = $indexPageModel->html_content;
                $content = $this->transformDokuWikiLinks($content);
                $extractedData = $this->extractH1Title($content);
                
                return Inertia::render('Wiki/Page', [
                    'page' => $indexPage,
                    'pageInfo' => $pageInfo,
                    'content' => $extractedData['content'],
                    'extractedTitle' => $extractedData['title'],
                    'subpages' => $indexPageModel->subpages,
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
            // Use database search instead of DokuWiki API
            $results = Page::where('title', 'like', "%{$query}%")
                ->orWhere('content', 'like', "%{$query}%")
                ->take(20)
                ->get()
                ->map(function ($page) {
                    return [
                        'id' => $page->page_id,
                        'title' => $page->title,
                        'href' => route('wiki.show', ['page' => $page->page_id]),
                        'excerpt' => $page->excerpt ?: substr(strip_tags($page->content), 0, 200),
                        'score' => 1, // Could implement proper scoring later
                    ];
                })
                ->toArray();
            
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
                'size' => $pageModel->size_bytes ?? strlen($pageModel->content ?? ''),
                'revision' => $pageModel->revision,
            ]
        ];
        
        $pageInfo = [
            'lastModified' => $pageModel->last_modified,
            'author' => 'DokuWiki',
            'size' => $pageModel->size_bytes ?? strlen($pageModel->content ?? ''),
            'revision' => $pageModel->revision,
            'permission' => $pageModel->permission,
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
            
            // Generate sitemap content using dynamic subpages
            $parentPage = Page::findByPageId($namespace);
            $subpages = $parentPage ? $parentPage->subpages : [];
            
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
        
        if ($pageId === 'start' || $pageId === 'index') {
            // For the main wiki page, just return empty breadcrumbs
            return $breadcrumbs;
        }
        
        // Split the page ID into parts
        $parts = explode(':', $pageId);
        
        // Special handling for index pages - skip the 'index' part and use page titles
        if (end($parts) === 'index') {
            array_pop($parts); // Remove 'index' from parts
            
            // For index pages, we want to show actual page titles from database
            $currentPath = '';
            foreach ($parts as $index => $part) {
                $currentPath = $currentPath ? $currentPath . ':' . $part : $part;
                $isLast = $index === count($parts) - 1;
                
                if ($isLast && $pageTitle) {
                    // Use the provided page title for the last part
                    $title = $pageTitle;
                } else {
                    // Try to get the title from the database for this namespace
                    $indexPageId = $currentPath . ':index';
                    $pageModel = Page::findByPageId($indexPageId);
                    
                    if ($pageModel && $pageModel->title) {
                        $title = $pageModel->title;
                    } else {
                        // Fallback to formatted part name
                        $title = ucfirst(str_replace(['_', '-'], ' ', $part));
                    }
                }
                
                // Only add href for non-last items
                $breadcrumbs[] = [
                    'title' => $title,
                    'href' => $isLast ? '' : route('wiki.show', ['page' => $currentPath]),
                ];
            }
        } else {
            // Normal page handling (non-index pages)
            $currentPath = '';
            foreach ($parts as $index => $part) {
                $currentPath = $currentPath ? $currentPath . ':' . $part : $part;
                $isLast = $index === count($parts) - 1;
                
                // For the last part, use the page title if available
                if ($isLast && $pageTitle) {
                    $title = $pageTitle;
                } else {
                    // Try to get title from database
                    $pageModel = Page::findByPageId($currentPath);
                    if ($pageModel && $pageModel->title) {
                        $title = $pageModel->title;
                    } else {
                        // Fallback to formatted part name
                        $title = ucfirst(str_replace(['_', '-'], ' ', $part));
                    }
                }
                
                // Only add href for non-last items (breadcrumbs don't link to current page)
                $breadcrumbs[] = [
                    'title' => $title,
                    'href' => $isLast ? '' : route('wiki.show', ['page' => $currentPath]),
                ];
            }
        }
        
        return $breadcrumbs;
    }

    private function showCurrentEurofurence(string $currentEF): \Inertia\Response
    {
        try {
            // Try to load the ef##:index page first, then fall back to ef##
            $indexPage = Page::findByPageId($currentEF . ':index');
            $mainPage = Page::findByPageId($currentEF);
            
            $pageModel = $indexPage ?: $mainPage;
            
            if (!$pageModel || !$pageModel->html_content) {
                throw new \Exception('Current EF page not found');
            }

            // Load page content and metadata
            $pageInfo = [
                'lastModified' => $pageModel->last_modified,
                'author' => 'DokuWiki',
                'size' => $pageModel->size_bytes ?? strlen($pageModel->content ?? ''),
                'revision' => $pageModel->revision,
                'permission' => $pageModel->permission,
            ];
            
            $content = $pageModel->html_content;
            $content = $this->transformDokuWikiLinks($content);
            $extractedData = $this->extractH1Title($content);

            // Get historical EFs for the bottom section
            $historicalEFs = $this->eurofurenceService->getOldEFs();

            // Add historical EFs section to the content
            $historicalSection = $this->generateHistoricalEFsHtml($historicalEFs);
            $content = $extractedData['content'] . $historicalSection;

            return Inertia::render('Wiki/Page', [
                'page' => $currentEF,
                'pageInfo' => $pageInfo,
                'content' => $content,
                'extractedTitle' => $extractedData['title'],
                'subpages' => $pageModel->subpages,
                'tableOfContents' => $pageModel->table_of_contents ?? [],
                'breadcrumbs' => $this->generateBreadcrumbs($currentEF, $extractedData['title']),
                'isCurrentEF' => true,
                'historicalEFs' => $historicalEFs,
            ]);
        } catch (\Exception $e) {
            // Fallback to regular page handling
            return $this->generateNamespaceSitemap($currentEF);
        }
    }

    private function generateHistoricalEFsHtml(array $historicalEFs): string
    {
        if (empty($historicalEFs)) {
            return '';
        }

        $html = '<div class="mt-8 pt-6 border-t border-gray-200">';
        $html .= '<h2 class="text-xl font-semibold mb-4">Looking for old Eurofurence?</h2>';
        $html .= '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">';

        foreach ($historicalEFs as $ef) {
            $html .= '<a href="' . $ef['url'] . '" class="block p-3 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors">';
            $html .= '<div class="font-medium text-sm">' . htmlspecialchars($ef['title']) . '</div>';
            $html .= '<div class="text-xs text-gray-500">EF' . $ef['year'] . '</div>';
            $html .= '</a>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

}