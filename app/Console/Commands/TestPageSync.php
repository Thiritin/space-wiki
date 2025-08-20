<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Services\DokuWikiService;
use Illuminate\Console\Command;

class TestPageSync extends Command
{
    protected $signature = 'wiki:test-sync {--page=start}';
    protected $description = 'Test sync for a single page with HTML content';

    public function handle()
    {
        $pageId = $this->option('page');
        $dokuWiki = app(DokuWikiService::class);
        
        try {
            $this->info("Testing sync for page: {$pageId}");
            
            // Get page info and content
            $pageInfo = $dokuWiki->getPageInfo($pageId);
            $content = $dokuWiki->getPage($pageId);
            $htmlContent = $dokuWiki->getPageHtml($pageId);
            
            if (empty($pageInfo)) {
                $this->error("Page info not found for: {$pageId}");
                return 1;
            }
            
            $this->info("Page info: " . json_encode($pageInfo));
            $this->info("Content length: " . strlen($content));
            $this->info("HTML content length: " . strlen($htmlContent));
            
            // Create/update page
            $page = Page::createFromDokuWiki($pageId, $pageInfo, $content, $htmlContent);
            
            $this->info("Page synced successfully!");
            $this->info("Database ID: " . $page->id);
            $this->info("Page ID: " . $page->page_id);
            $this->info("Title: " . $page->title);
            $this->info("Has HTML content: " . ($page->html_content ? 'Yes' : 'No'));
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Sync failed: " . $e->getMessage());
            return 1;
        }
    }
}