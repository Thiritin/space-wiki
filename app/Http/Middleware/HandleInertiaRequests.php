<?php

namespace App\Http\Middleware;

use App\Models\UserTeam;
use App\Services\DokuWikiService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'teams' => $this->getTeamNamespaces($request),
            'userTeams' => $this->getUserTeams($request),
        ];
    }

    /**
     * Get team namespaces with caching for performance
     */
    private function getTeamNamespaces(Request $request): array
    {
        // Only load teams for authenticated users
        if (!$request->user()) {
            return [];
        }

        // Temporarily disable cache for debugging
        Cache::forget('team_namespaces');
        return Cache::remember('team_namespaces', 300, function () {
            try {
                \Log::info('HandleInertia: Starting team namespace loading');
                $dokuwikiService = app(DokuWikiService::class);
                
                // Get team namespace pages specifically with high depth to catch subpages
                $teamPages = $dokuwikiService->getNamespacePages('team', 10);
                \Log::info('HandleInertia: Got team namespace pages', ['count' => count($teamPages)]);
                
                // Also get all pages as fallback
                $allPages = $dokuwikiService->getAllPages();
                \Log::info('HandleInertia: Got all pages', ['count' => count($allPages)]);
                
                // Combine both results
                $combinedPages = array_merge($teamPages, $allPages);
                
                // Remove duplicates based on page ID
                $uniquePages = [];
                foreach ($combinedPages as $page) {
                    $pageId = $page['id'] ?? '';
                    if (!isset($uniquePages[$pageId])) {
                        $uniquePages[$pageId] = $page;
                    }
                }
                
                $teamNamespaces = [];
                $realNamespaces = []; // Track teams that have actual subpages
                
                // First pass: find all teams with actual subpages (3+ parts)
                foreach ($uniquePages as $page) {
                    $pageId = $page['id'] ?? '';
                    
                    if (strpos($pageId, 'team:') === 0) {
                        $parts = explode(':', $pageId);
                        
                        if (count($parts) >= 3) {
                            $teamName = $parts[1];
                            $realNamespaces[$teamName] = true;
                            
                            if (!isset($teamNamespaces[$teamName])) {
                                $teamNamespaces[$teamName] = [
                                    'name' => $teamName,
                                    'displayName' => ucfirst(str_replace(['_', '-'], ' ', $teamName)),
                                    'href' => route('wiki.show', ['page' => "team:{$teamName}"]),
                                    'type' => 'namespace',
                                    'priority' => 1 // Higher priority for real namespaces
                                ];
                            }
                        }
                    }
                }
                
                // Second pass: add standalone team pages only if they don't have subpages
                foreach ($uniquePages as $page) {
                    $pageId = $page['id'] ?? '';
                    
                    if (strpos($pageId, 'team:') === 0) {
                        $parts = explode(':', $pageId);
                        
                        if (count($parts) === 2) {
                            $teamName = $parts[1];
                            
                            // Only add if this team doesn't already have subpages
                            if (!isset($realNamespaces[$teamName])) {
                                // Skip obvious content pages
                                $skipList = ['index', 'start', 'main', 'home', 'presse', 'press', 'news', 
                                           'dance_competition', 'dance_competitions', 'event', 'events'];
                                
                                if (!in_array(strtolower($teamName), $skipList)) {
                                    // Only include if it doesn't contain obvious content indicators
                                    $contentIndicators = ['competition', 'event', 'news', 'press', 'announcement'];
                                    $isContent = false;
                                    
                                    foreach ($contentIndicators as $indicator) {
                                        if (str_contains(strtolower($teamName), $indicator)) {
                                            $isContent = true;
                                            break;
                                        }
                                    }
                                    
                                    if (!$isContent && strlen($teamName) >= 2 && strlen($teamName) <= 30) {
                                        if (!isset($teamNamespaces[$teamName])) {
                                            $teamNamespaces[$teamName] = [
                                                'name' => $teamName,
                                                'displayName' => ucfirst(str_replace(['_', '-'], ' ', $teamName)),
                                                'href' => route('wiki.show', ['page' => "team:{$teamName}"]),
                                                'type' => 'page',
                                                'priority' => 2 // Lower priority for standalone pages
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Sort by priority first, then alphabetically
                uasort($teamNamespaces, function($a, $b) {
                    if ($a['priority'] !== $b['priority']) {
                        return $a['priority'] <=> $b['priority'];
                    }
                    return strcmp($a['displayName'], $b['displayName']);
                });
                
                // Remove priority from response
                $result = array_map(function($team) {
                    unset($team['priority']);
                    return $team;
                }, array_values($teamNamespaces));
                
                \Log::info('HandleInertia: Final team namespaces result', ['count' => count($result), 'teams' => $result]);
                
                return $result;
                
            } catch (\Exception $e) {
                \Log::error('Failed to load team namespaces in HandleInertia', [
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Get user's selected teams
     */
    private function getUserTeams(Request $request): array
    {
        // Only load user teams for authenticated users
        if (!$request->user()) {
            return [];
        }

        return UserTeam::forUser($request->user()->id)
            ->ordered()
            ->get()
            ->map(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->team_name,
                    'displayName' => $team->team_display_name,
                    'href' => $team->team_href,
                    'type' => $team->team_type,
                    'sort_order' => $team->sort_order,
                ];
            })
            ->toArray();
    }

}
