<?php

namespace App\Services;

use App\Models\Page;
use Illuminate\Support\Facades\Cache;

class EurofurenceService
{
    /**
     * Get the current (highest numbered) Eurofurence
     */
    public function getCurrentEF(): ?string
    {
        return Cache::remember('current_ef', 3600, function () {
            $efPages = Page::where('page_id', 'regexp', '^ef[0-9]+')
                ->get(['page_id']);
            
            $years = [];
            foreach ($efPages as $page) {
                if (preg_match('/^ef(\d+)/', $page->page_id, $matches)) {
                    $years[] = (int)$matches[1];
                }
            }
            
            if (empty($years)) {
                return null;
            }
            
            $maxYear = max($years);
            return 'ef' . $maxYear;
        });
    }

    /**
     * Get all historical Eurofurence entries
     */
    public function getHistoricalEFs(): array
    {
        return Cache::remember('historical_efs', 3600, function () {
            $efPages = Page::where('page_id', 'regexp', '^ef[0-9]+$')
                ->orWhere('page_id', 'regexp', '^ef[0-9]+:index$')
                ->orderBy('page_id')
                ->get(['page_id', 'title']);
            
            $efEntries = [];
            $processedYears = [];
            
            foreach ($efPages as $page) {
                if (preg_match('/^ef(\d+)(?::index)?$/', $page->page_id, $matches)) {
                    $year = (int)$matches[1];
                    
                    // Skip if we already processed this year
                    if (in_array($year, $processedYears)) {
                        continue;
                    }
                    
                    $processedYears[] = $year;
                    
                    // Prefer ef##:index if available, otherwise use ef##
                    $indexPage = Page::where('page_id', 'ef' . $year . ':index')->first();
                    $mainPage = Page::where('page_id', 'ef' . $year)->first();
                    
                    $targetPage = $indexPage ?: $mainPage;
                    
                    if ($targetPage) {
                        $efEntries[] = [
                            'year' => $year,
                            'id' => 'ef' . $year,
                            'title' => $targetPage->title ?: 'Eurofurence ' . $year,
                            'url' => "/wiki/ef{$year}",
                            'page_exists' => true
                        ];
                    }
                }
            }
            
            // Sort by year descending (newest first)
            usort($efEntries, function ($a, $b) {
                return $b['year'] - $a['year'];
            });
            
            return $efEntries;
        });
    }

    /**
     * Get non-current historical EFs (excluding the current one)
     */
    public function getOldEFs(): array
    {
        $current = $this->getCurrentEF();
        $all = $this->getHistoricalEFs();
        
        return array_filter($all, function ($ef) use ($current) {
            return $ef['id'] !== $current;
        });
    }

    /**
     * Clear cache when EF data might have changed
     */
    public function clearCache(): void
    {
        Cache::forget('current_ef');
        Cache::forget('historical_efs');
    }
}