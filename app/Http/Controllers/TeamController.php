<?php

namespace App\Http\Controllers;

use App\Services\DokuWikiService;

class TeamController extends Controller
{
    public function __construct(
        private DokuWikiService $dokuwikiService
    ) {}

    public function debugTeams()
    {
        try {
            // First check if DokuWiki connection works at all
            \Log::info('debugTeams: Starting debug');
            
            // Get all pages (might be shallow)
            $allPages = $this->dokuwikiService->getAllPages();
            \Log::info('debugTeams: Got all pages', ['count' => count($allPages)]);
            
            // Also try to get team namespace specifically with depth
            $teamNamespacePages = $this->dokuwikiService->getNamespacePages('team', 10); // High depth
            \Log::info('debugTeams: Got team namespace pages', ['count' => count($teamNamespacePages)]);
            
            $teamPages = [];
            $allTeamPages = [];
            $teamNamespaceOnlyPages = [];
            
            // Check regular getAllPages for team: pages
            foreach ($allPages as $page) {
                $pageId = $page['id'] ?? '';
                
                // Log first 10 pages to see what we're getting
                if (count($allTeamPages) < 10) {
                    $allTeamPages[] = $pageId;
                }
                
                if (strpos($pageId, 'team:') === 0) {
                    $teamPages[] = [
                        'id' => $pageId,
                        'parts' => explode(':', $pageId),
                        'parts_count' => count(explode(':', $pageId)),
                        'title' => $page['title'] ?? '',
                        'size' => $page['size'] ?? 0,
                        'source' => 'getAllPages'
                    ];
                }
            }
            
            // Check team namespace specific call
            foreach ($teamNamespacePages as $page) {
                $pageId = $page['id'] ?? '';
                
                if (strpos($pageId, 'team:') === 0) {
                    $teamNamespaceOnlyPages[] = [
                        'id' => $pageId,
                        'parts' => explode(':', $pageId),
                        'parts_count' => count(explode(':', $pageId)),
                        'title' => $page['title'] ?? '',
                        'size' => $page['size'] ?? 0,
                        'source' => 'getNamespacePages'
                    ];
                }
            }
            
            \Log::info('debugTeams: Found team pages', [
                'getAllPages_team_count' => count($teamPages),
                'getNamespacePages_team_count' => count($teamNamespaceOnlyPages)
            ]);
            
            return response()->json([
                'success' => true,
                'total_pages_getAllPages' => count($allPages),
                'total_pages_teamNamespace' => count($teamNamespacePages),
                'first_10_pages_sample' => $allTeamPages,
                'team_pages_from_getAllPages' => $teamPages,
                'team_pages_from_namespace' => $teamNamespaceOnlyPages,
                'summary' => [
                    'getAllPages_team_count' => count($teamPages),
                    'namespace_team_count' => count($teamNamespaceOnlyPages),
                    'total_unique_team_pages' => count(array_unique(array_merge(
                        array_column($teamPages, 'id'),
                        array_column($teamNamespaceOnlyPages, 'id')
                    )))
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('debugTeams: Exception occurred', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function getNamespaces()
    {
        try {
            // Get team namespace pages specifically with high depth to catch subpages
            $teamPages = $this->dokuwikiService->getNamespacePages('team', 10);
            
            // Also get all pages as fallback
            $allPages = $this->dokuwikiService->getAllPages();
            
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
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Unable to load team namespaces: ' . $e->getMessage(),
                'teams' => [],
            ], 500);
        }
    }
}