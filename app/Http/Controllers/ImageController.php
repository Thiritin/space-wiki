<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageController extends Controller
{
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
            $contentType = $response->header('Content-Type') ?: 'image/jpeg';
            $lastModified = gmdate('D, d M Y H:i:s \G\M\T');
            
            // Cache the image for 24 hours (encode binary data as base64)
            Cache::put($cacheKey, [
                'content' => base64_encode($content),
                'contentType' => $contentType,
                'lastModified' => $lastModified,
            ], 1440); // 24 hours
            
            Log::info('Returning image response', ['content_type' => $contentType, 'content_length' => strlen($content)]);
            
            return response($content)
                ->header('Content-Type', $contentType)
                ->header('Cache-Control', 'public, max-age=31536000') // 1 year for browser cache
                ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000))
                ->header('Last-Modified', $lastModified)
                ->header('ETag', '"' . md5($content) . '"');
                
        } catch (\Exception $e) {
            Log::error('ProxyImage exception', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            abort(404, 'Image not found');
        }
    }
}