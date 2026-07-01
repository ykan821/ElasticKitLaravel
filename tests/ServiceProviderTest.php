<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Tests;

use Elastic\Elasticsearch\ClientInterface;
use ElasticKit\Index\Support\ClientManager;
use ElasticKit\Index\Support\Pagination;
use ElasticKit\Laravel\ElasticKitServiceProvider;
use Illuminate\Http\Request;
use RuntimeException;

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

    public function testDefaultClientIsRegistered(): void
    {
        $this->assertInstanceOf(ClientInterface::class, ClientManager::get('default'));
    }

    public function testPaginationResolversAreRegistered(): void
    {
        $this->assertNotNull(Pagination::getPageResolver());
        $this->assertNotNull(Pagination::getPaginatorResolver());
    }

    public function testRegisteredPageResolverExtractsFromRequest(): void
    {
        $this->app->instance(Request::class, Request::create('/', 'GET', ['page' => 3, 'per_page' => 20]));

        [$page, $perPage] = (Pagination::getPageResolver())();

        $this->assertSame(3, $page);
        $this->assertSame(20, $perPage);
    }

    public function testRegisterClientsFailsFastOnEmptyConnections(): void
    {
        config(['elastickit.connections' => []]);

        $provider = new ElasticKitServiceProvider($this->app);
        $register = new \ReflectionMethod($provider, 'registerClients');
        $register->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No elastickit connections configured');

        $register->invoke($provider);
    }

    public function testMisconfiguredConnectionDefersErrorUntilUse(): void
    {
        config(['elastickit.connections' => ['default' => ['retries' => -1]]]);

        $provider = new ElasticKitServiceProvider($this->app);
        $register = new \ReflectionMethod($provider, 'registerClients');
        $register->setAccessible(true);

        // registerClients must NOT throw — the client is built lazily.
        $register->invoke($provider);

        $client = ClientManager::get('default');
        $this->assertInstanceOf(ClientInterface::class, $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("connection 'default'");

        // First use triggers the factory → setRetries(-1) throws.
        $client->info();
    }
}
