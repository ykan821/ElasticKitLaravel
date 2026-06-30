<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Tests;

use ElasticKit\Index\Index;
use ElasticKit\Laravel\Console\IndexResolver;
use ElasticKit\Laravel\ElasticKitServiceProvider;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class ConsoleTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ElasticKitServiceProvider::class];
    }

    public function testResolverResolvesFqcn(): void
    {
        $index = app(IndexResolver::class)->resolve(StubIndex::class);

        $this->assertInstanceOf(StubIndex::class, $index);
    }

    public function testResolverResolvesAliasFromConfig(): void
    {
        config(['elastickit.indices' => ['stub' => StubIndex::class]]);

        $this->assertInstanceOf(StubIndex::class, app(IndexResolver::class)->resolve('stub'));
    }

    public function testResolverThrowsForUnknown(): void
    {
        $this->expectException(RuntimeException::class);

        app(IndexResolver::class)->resolve('No\Such\Index');
    }

    public function testCommandsAreRegistered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('elastickit:rebuild', $commands);
        $this->assertArrayHasKey('elastickit:rebuild:clean', $commands);
        $this->assertArrayHasKey('elastickit:rebuild:rollback', $commands);
        $this->assertArrayHasKey('elastickit:rebuild:unlock', $commands);
        $this->assertArrayHasKey('elastickit:index:create', $commands);
        $this->assertArrayHasKey('elastickit:index:delete', $commands);
        $this->assertArrayHasKey('elastickit:index:exists', $commands);
        $this->assertArrayHasKey('elastickit:mapping:put', $commands);
    }
}

class StubIndex extends Index
{
    protected string $name = 'stub';
}
