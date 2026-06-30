<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Tests;

use ElasticKit\Index\Results;
use ElasticKit\Index\Support\Pagination;
use ElasticKit\Laravel\Pagination\LaravelPagination;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Unit-tests the resolver factories directly, without booting the provider.
 */
class ResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bindRequest([]);
    }

    public function testPaginatorResolverReturnsLaravelPaginator(): void
    {
        Pagination::setPaginatorResolver(LaravelPagination::paginatorResolver('page'));

        $response = [
            'hits' => [
                'total' => ['value' => 42, 'relation' => 'eq'],
                'hits' => [
                    ['_id' => '1', '_source' => ['title' => 'A']],
                    ['_id' => '2', '_source' => ['title' => 'B']],
                ],
            ],
        ];

        $paginator = (new Results($response))->paginate(3, 10)->toPaginator();

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(42, $paginator->total());
        $this->assertSame(10, $paginator->perPage());
        $this->assertSame(3, $paginator->currentPage());
        $this->assertCount(2, $paginator->items());
    }

    public function testPageResolverReadsPageAndPerPageFromRequest(): void
    {
        $this->bindRequest(['page' => '3', 'per_page' => '10']);

        [$page, $perPage] = (LaravelPagination::pageResolver(15))();

        $this->assertSame(3, $page);
        $this->assertSame(10, $perPage);
    }

    public function testPageResolverFallsBackToDefaultPerPage(): void
    {
        [, $perPage] = (LaravelPagination::pageResolver(15))();

        $this->assertSame(15, $perPage);
    }

    public function testPageResolverClampsPageBelowOne(): void
    {
        $this->bindRequest(['page' => '0']);

        [$page] = (LaravelPagination::pageResolver(15))();

        $this->assertSame(1, $page);
    }

    private function bindRequest(array $query): void
    {
        $this->app->instance(Request::class, Request::create('/', 'GET', $query));
    }
}
