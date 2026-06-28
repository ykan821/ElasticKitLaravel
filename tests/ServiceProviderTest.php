<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Tests;

use Elastic\Elasticsearch\ClientInterface;
use ElasticKit\Index\Support\ClientManager;
use ElasticKit\Index\Support\Pagination;
use ElasticKit\Laravel\ElasticKitServiceProvider;
use Illuminate\Http\Request;

/**
 * Boots the provider via testbench and asserts it wires ElasticKit up.
 */
class ServiceProviderTest extends TestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getPackageProviders($app): array
    {
        return [ElasticKitServiceProvider::class];
    }

    public function test_default_client_is_registered(): void
    {
        $this->assertInstanceOf(ClientInterface::class, ClientManager::get('default'));
    }

    public function test_pagination_resolvers_are_registered(): void
    {
        $this->assertNotNull(Pagination::getPageResolver());
        $this->assertNotNull(Pagination::getPaginatorResolver());
    }

    public function test_registered_page_resolver_extracts_from_request(): void
    {
        $this->app->instance(Request::class, Request::create('/', 'GET', ['page' => 3, 'per_page' => 20]));

        [$page, $perPage] = (Pagination::getPageResolver())();

        $this->assertSame(3, $page);
        $this->assertSame(20, $perPage);
    }
}
