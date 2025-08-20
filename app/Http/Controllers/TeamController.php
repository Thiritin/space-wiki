<?php

namespace App\Http\Controllers;

use App\Models\Page;

class TeamController extends Controller
{
    public function debugTeams()
    {
        try {
            // Get all team pages from database
            \Log::info('debugTeams: Starting debug (database-only)');
            
            $allTeamPages = Page::where('page_id', 'like', 'team:%')->get();
            \Log::info('debugTeams: Got team pages from database', ['count' => count($allTeamPages)]);
            
            $teamPages = [];
            $samplePages = [];
            
            foreach ($allTeamPages as $page) {
                $pageId = $page->page_id;
                
                // Log first 10 pages to see what we're getting
                if (count($samplePages) < 10) {
                    $samplePages[] = $pageId;
                }
                
                $teamPages[] = [
                    'id' => $pageId,
                    'parts' => explode(':', $pageId),
                    'parts_count' => count(explode(':', $pageId)),
                    'title' => $page->title,
                    'size' => $page->size_bytes ?? strlen($page->content ?? ''),
                    'source' => 'database'
                ];
            }
            
            \Log::info('debugTeams: Found team pages', [
                'team_count' => count($teamPages)
            ]);
            
            return response()->json([
                'success' => true,
                'total_pages' => count($allTeamPages),
                'first_10_pages_sample' => $samplePages,
                'team_pages' => $teamPages,
                'summary' => [
                    'team_count' => count($teamPages)
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
            // Get all team pages from database
            $teamPages = Page::where('page_id', 'like', 'team:%')->get();
            
            $teamNamespaces = [];
            $realNamespaces = []; // Track teams that have actual subpages
            
            // First pass: find all teams with actual subpages (3+ parts)
            foreach ($teamPages as $page) {
                $pageId = $page->page_id;
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
            
            // Second pass: add standalone team pages only if they don't have subpages
            foreach ($teamPages as $page) {
                $pageId = $page->page_id;
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