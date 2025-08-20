<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Wiki Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the DokuWiki frontend application.
    |
    */

    'allowed_group_ids' => array_filter(explode(',', env('WIKI_ALLOWED_GROUP_IDS', ''))),

    'dokuwiki' => [
        'uri' => env('DOKUWIKI_URI'),
        'username' => env('DOKUWIKI_USERNAME'),
        'password' => env('DOKUWIKI_PASSWORD'),
        'jsonrpc_endpoint' => env('DOKUWIKI_JSONRPC_ENDPOINT', '/lib/exe/xmlrpc.php'),
    ],

    'typesense' => [
        'host' => env('TYPESENSE_HOST', 'localhost'),
        'port' => env('TYPESENSE_PORT', '8108'),
        'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
        'api_key' => env('TYPESENSE_API_KEY'),
        'collection_name' => env('TYPESENSE_COLLECTION_NAME', 'wiki_pages'),
    ],

];