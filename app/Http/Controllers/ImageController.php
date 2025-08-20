<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageController extends Controller
{
    /**
     * Handle DokuWiki detail.php requests for image display
     */
    public function detail(Request $request)
    {
        Log::info('=== DOKUWIKI DETAIL REQUEST START ===', [
            'url' => $request->fullUrl(),
            'query_string' => $request->getQueryString(),
            'all_params' => $request->all()
        ]);
        
        
        try {
            // Get the media parameter (required)
            $imagePath = $request->get('media');
            if (!$imagePath) {
                Log::error('Media parameter missing in detail request', ['params' => $request->all()]);
                abort(404, 'Media parameter required');
            }
            
            // Parse DokuWiki media path and convert to fetch.php parameters
            $fetchParams = [
                'media' => $imagePath,
                'w' => $request->get('w', ''),
                'h' => $request->get('h', ''),
                'cache' => $request->get('cache', 'cache'),
            ];
            
            // Remove empty parameters
            $fetchParams = array_filter($fetchParams, function($value) {
                return $value !== '';
            });
            
            Log::info('Converted detail request to fetch parameters', ['params' => $fetchParams]);
            
            // Build the DokuWiki fetch URL
            $dokuwikiUri = rtrim(config('wiki.dokuwiki.uri'), '/');
            $imageUrl = $dokuwikiUri . '/lib/exe/fetch.php?' . http_build_query($fetchParams);
            
            Log::info('Built DokuWiki fetch URL', ['url' => $imageUrl]);
            
            // Check cache first
            $cacheKey = 'wiki_detail_' . md5(serialize($fetchParams));
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData) {
                $content = base64_decode($cachedData['content']);
                return response($content)
                    ->header('Content-Type', $cachedData['contentType'])
                    ->header('Cache-Control', 'public, max-age=31536000')
                    ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000))
                    ->header('Last-Modified', $cachedData['lastModified'])
                    ->header('ETag', '"' . md5($content) . '"');
            }
            
            // Fetch image from DokuWiki
            Log::info('Fetching image from DokuWiki', ['url' => $imageUrl]);
            $response = Http::withBasicAuth(
                config('wiki.dokuwiki.username'),
                config('wiki.dokuwiki.password')
            )->timeout(30)->get($imageUrl);
            
            Log::info('DokuWiki fetch response', [
                'status' => $response->status(), 
                'content_type' => $response->header('Content-Type'),
                'content_length' => strlen($response->body())
            ]);
            
            if (!$response->successful()) {
                Log::error('DokuWiki fetch failed', ['status' => $response->status(), 'body' => substr($response->body(), 0, 200)]);
                
                // Return a placeholder image if DokuWiki is not available
                return $this->generatePlaceholderImage($imagePath);
            }
            
            $content = $response->body();
            $contentType = $response->header('Content-Type') ?: 'image/jpeg';
            $lastModified = gmdate('D, d M Y H:i:s \G\M\T');
            
            // Cache the image for 24 hours
            Cache::put($cacheKey, [
                'content' => base64_encode($content),
                'contentType' => $contentType,
                'lastModified' => $lastModified,
            ], 1440); // 24 hours
            
            Log::info('Returning detail image response', [
                'content_type' => $contentType, 
                'content_length' => strlen($content)
            ]);
            
            return response($content)
                ->header('Content-Type', $contentType)
                ->header('Cache-Control', 'public, max-age=31536000')
                ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000))
                ->header('Last-Modified', $lastModified)
                ->header('ETag', '"' . md5($content) . '"');
                
        } catch (\Exception $e) {
            Log::error('DokuWiki detail exception', [
                'message' => $e->getMessage(), 
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return a placeholder image instead of aborting
            $imagePath = $request->get('media', 'unknown');
            return $this->generatePlaceholderImage($imagePath);
        }
    }

    public function __invoke(Request $request)
    {
        Log::info('=== PROXY IMAGE START ===', [
            'url' => $request->fullUrl(),
            'query_string' => $request->getQueryString(),
            'all_params' => $request->all()
        ]);
        
        // Test: Return a simple response to see if method works
        if ($request->get('test')) {
            return response('TEST IMAGE PROXY WORKING')
                ->header('Content-Type', 'text/plain');
        }
        
        try {
            // Get the media parameter (required)
            $imagePath = $request->get('media');
            if (!$imagePath) {
                Log::error('Media parameter missing', ['params' => $request->all()]);
                abort(404, 'Media parameter required');
            }
            
            // Build the DokuWiki image URL with all original parameters
            $dokuwikiUri = rtrim(config('wiki.dokuwiki.uri'), '/');
            $imageUrl = $dokuwikiUri . '/lib/exe/fetch.php?' . $request->getQueryString();
            
            Log::info('Built DokuWiki URL', ['url' => $imageUrl]);
            
            // Check cache first (use the full query string for cache key)
            $cacheKey = 'wiki_image_' . md5($request->getQueryString());
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData) {
                $content = base64_decode($cachedData['content']);
                return response($content)
                    ->header('Content-Type', $cachedData['contentType'])
                    ->header('Cache-Control', 'public, max-age=31536000') // 1 year
                    ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000))
                    ->header('Last-Modified', $cachedData['lastModified'])
                    ->header('ETag', '"' . md5($content) . '"');
            }
            
            // Fetch image from DokuWiki
            Log::info('Fetching from DokuWiki', ['url' => $imageUrl]);
            $response = Http::withBasicAuth(
                config('wiki.dokuwiki.username'),
                config('wiki.dokuwiki.password')
            )->timeout(30)->get($imageUrl);
            
            Log::info('DokuWiki response', ['status' => $response->status(), 'headers' => $response->headers()]);
            
            if (!$response->successful()) {
                Log::error('DokuWiki fetch failed', ['status' => $response->status(), 'body' => $response->body()]);
                abort(404, 'Image not found');
            }
            
            $content = $response->body();
            $contentType = $response->header('Content-Type') ?: $this->guessContentType($imagePath);
            $lastModified = gmdate('D, d M Y H:i:s \G\M\T');
            
            // Cache the media for 24 hours (encode binary data as base64)
            Cache::put($cacheKey, [
                'content' => base64_encode($content),
                'contentType' => $contentType,
                'lastModified' => $lastModified,
            ], 1440); // 24 hours
            
            Log::info('Returning media response', ['content_type' => $contentType, 'content_length' => strlen($content)]);
            
            $response = response($content)
                ->header('Content-Type', $contentType)
                ->header('Cache-Control', 'public, max-age=31536000') // 1 year for browser cache
                ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000))
                ->header('Last-Modified', $lastModified)
                ->header('ETag', '"' . md5($content) . '"');
            
            // Add download headers for non-image content
            if (!str_starts_with($contentType, 'image/')) {
                $filename = basename($imagePath);
                $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            }
            
            return $response;
                
        } catch (\Exception $e) {
            Log::error('ProxyMedia exception', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            abort(404, 'Media not found');
        }
    }

    /**
     * Generate a placeholder image when DokuWiki is not available
     */
    private function generatePlaceholderImage(string $imagePath): \Illuminate\Http\Response
    {
        // Create a simple SVG placeholder
        $filename = basename($imagePath);
        $width = 400;
        $height = 300;
        $rectWidth = $width - 20;
        $rectHeight = $height - 20;
        
        $svg = <<<SVG
<svg width="{$width}" height="{$height}" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="#f3f4f6"/>
    <rect x="10" y="10" width="{$rectWidth}" height="{$rectHeight}" fill="none" stroke="#d1d5db" stroke-width="2" stroke-dasharray="5,5"/>
    <text x="50%" y="40%" text-anchor="middle" font-family="Arial, sans-serif" font-size="16" fill="#6b7280">
        Image not available
    </text>
    <text x="50%" y="60%" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" fill="#9ca3af">
        {$filename}
    </text>
</svg>
SVG;

        return response($svg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=3600') // Cache for 1 hour only
            ->header('Last-Modified', gmdate('D, d M Y H:i:s \G\M\T'));
    }
    
    /**
     * Guess content type based on file extension
     */
    private function guessContentType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            
            // Documents
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            
            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            
            // Text
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'xml' => 'text/xml',
            'json' => 'application/json',
            
            // Audio/Video
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}