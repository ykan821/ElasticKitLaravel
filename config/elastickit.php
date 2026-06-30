<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    | Used when an Index subclass does not override its $connection.
    */

    'default' => env('ELASTICKIT_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Index aliases
    |--------------------------------------------------------------------------
    | Map a short alias to an Index class FQCN, so Artisan commands accept the
    | alias: `php artisan elastickit:rebuild products`. Anything not listed here
    | is resolved as a class FQCN directly.
    */

    'indices' => [
        // 'products' => \App\Search\ProductIndex::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    | Each entry is registered via Index::setClient($client, $name).
    | At least 'default' is required; add more for multi-cluster setups.
    |
    | Auth: use either api_key (+ optional api_key_id) for Elastic Cloud /
    | serverless, or username/password for a traditional secured cluster.
    */

    'connections' => [
        'default' => [
            'hosts'             => [env('ELASTICKIT_HOST', 'http://localhost:9200')],
            'cloud_id'          => env('ELASTICKIT_CLOUD_ID'),
            'api_key'           => env('ELASTICKIT_API_KEY'),
            'api_key_id'        => env('ELASTICKIT_API_KEY_ID'),
            'username'          => env('ELASTICKIT_USERNAME'),
            'password'          => env('ELASTICKIT_PASSWORD'),
            'retries'           => (int) env('ELASTICKIT_RETRIES', 2),
            'ssl_verification'  => (bool) env('ELASTICKIT_SSL_VERIFICATION', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    | page_name: the query-string key for the current page (?page=).
    | default_per_page: fallback when the request omits ?per_page; capped per
    |   index by its maxPerPage().
    */

    'paginator' => [
        'page_name'        => 'page',
        'default_per_page' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rebuild
    |--------------------------------------------------------------------------
    | on_error: an invokable class-string (or any callable) forwarded to
    |   Rebuild::onError() when a bulk import batch has errors. The handler
    |   receives (array $response, array $actions, Bulk $newbulk). Return to
    |   drop the failed items and continue, throw to abort, or re-import on
    |   $newbulk to retry. null (default) aborts on the first error.
    */
    'rebuild' => [
        'on_error' => null,

        // Custom Artisan command class for elastickit:rebuild — must extend
        // ElasticKit\Laravel\Console\RebuildCommand and keep the command name.
        // null uses the package default.
        'command' => null,
    ],

];
