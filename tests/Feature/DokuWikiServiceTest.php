<?php

use App\Services\DokuWikiService;
use Illuminate\Support\Facades\Http;

it('uses bearer token auth when token is configured', function () {
    config([
        'wiki.dokuwiki.uri' => 'https://wiki.example.com',
        'wiki.dokuwiki.token' => 'test-jwt-token',
        'wiki.dokuwiki.username' => '',
        'wiki.dokuwiki.password' => '',
        'wiki.dokuwiki.jsonrpc_endpoint' => '/lib/exe/jsonrpc.php',
    ]);

    Http::fake([
        'wiki.example.com/*' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 'test',
            'result' => 'ok',
        ]),
    ]);

    $service = new DokuWikiService;
    $service->makeRequest('core.getVersion');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer test-jwt-token');
    });
});

it('uses basic auth when no token is configured', function () {
    config([
        'wiki.dokuwiki.uri' => 'https://wiki.example.com',
        'wiki.dokuwiki.token' => null,
        'wiki.dokuwiki.username' => 'testuser',
        'wiki.dokuwiki.password' => 'testpass',
        'wiki.dokuwiki.jsonrpc_endpoint' => '/lib/exe/jsonrpc.php',
    ]);

    Http::fake([
        'wiki.example.com/*' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 'test',
            'result' => 'ok',
        ]),
    ]);

    $service = new DokuWikiService;
    $service->makeRequest('core.getVersion');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization')
            && str_starts_with($request->header('Authorization')[0], 'Basic ');
    });
});

it('throws on failed api response', function () {
    config([
        'wiki.dokuwiki.uri' => 'https://wiki.example.com',
        'wiki.dokuwiki.token' => 'test-token',
        'wiki.dokuwiki.username' => '',
        'wiki.dokuwiki.password' => '',
        'wiki.dokuwiki.jsonrpc_endpoint' => '/lib/exe/jsonrpc.php',
    ]);

    Http::fake([
        'wiki.example.com/*' => Http::response('Server Error', 500),
    ]);

    $service = new DokuWikiService;
    $service->makeRequest('core.getVersion');
})->throws(Exception::class, 'DokuWiki API request failed with status 500');

it('throws on api error response', function () {
    config([
        'wiki.dokuwiki.uri' => 'https://wiki.example.com',
        'wiki.dokuwiki.token' => 'test-token',
        'wiki.dokuwiki.username' => '',
        'wiki.dokuwiki.password' => '',
        'wiki.dokuwiki.jsonrpc_endpoint' => '/lib/exe/jsonrpc.php',
    ]);

    Http::fake([
        'wiki.example.com/*' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 'test',
            'error' => [
                'code' => -32601,
                'message' => 'Method does not exist',
            ],
        ]),
    ]);

    $service = new DokuWikiService;
    $service->makeRequest('invalid.method');
})->throws(Exception::class, 'Method does not exist');
