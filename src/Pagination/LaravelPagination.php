<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Pagination;

use ElasticKit\Index\Results;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Builds the page and paginator resolvers bridging ElasticKit to Laravel.
 *
 * Resolvers are plain callables matching the signatures in
 * ElasticKit\Index\Support\Pagination, so the provider stays thin and these
 * can be unit-tested in isolation.
 */
class LaravelPagination
{
    /**
     * Resolver that extracts [$page, $perPage] from the current request.
     *
     * Consumed by Search::paginate() when called with no arguments. perPage is
     * still capped per index by maxPerPage(), so clamping here only guards the
     * lower bound.
     *
     * @param int $defaultPerPage fallback when the request omits the per-page key
     * @param string $pageName query-string key for the page number
     * @param string $perPageName query-string key for the page size
     * @return callable(): array{0:int, 1:int}
     */
    public static function pageResolver(
        int $defaultPerPage,
        string $pageName = 'page',
        string $perPageName = 'per_page'
    ): callable {
        return static function () use ($defaultPerPage, $pageName, $perPageName): array {
            $request = app(Request::class);

            $page = max(1, (int) $request->input($pageName, 1));
            $perPage = max(1, (int) $request->input($perPageName, $defaultPerPage));

            return [$page, $perPage];
        };
    }

    /**
     * Resolver that wraps a Results instance into a Laravel paginator.
     *
     * @param string $pageName query-string key used in generated pagination links
     * @return callable(Results, int, int): LengthAwarePaginator
     */
    public static function paginatorResolver(string $pageName = 'page'): callable
    {
        return static function (Results $results, int $page, int $perPage) use ($pageName): LengthAwarePaginator {
            return new LengthAwarePaginator(
                $results->items(),
                $results->total(),
                $perPage,
                $page,
                [
                    'path' => app(Request::class)->url(),
                    'pageName' => $pageName,
                ]
            );
        };
    }
}
