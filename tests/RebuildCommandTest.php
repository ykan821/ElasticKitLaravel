<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Tests;

use ElasticKit\Index\Bulk;
use ElasticKit\Index\Index;
use ElasticKit\Laravel\Console\HasRebuildErrorHandler;
use ElasticKit\Laravel\Console\IndexResolver;
use ElasticKit\Laravel\Console\RebuildCommand;
use ElasticKit\Laravel\ElasticKitServiceProvider;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use RuntimeException;

class RebuildCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ElasticKitServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Exercise the command-replacement path across the whole class.
        $app['config']->set('elastickit.rebuild.command', StubRebuildCommand::class);
    }

    public function testParseContextParsesKeyValuePairs(): void
    {
        $result = $this->invokeProtected($this->command(), 'parseContext', [
            ['since=2026-06-01', 'limit=1000', 'nested=a=b=c'],
        ]);

        $this->assertSame([
            'since' => '2026-06-01',
            'limit' => '1000',
            'nested' => 'a=b=c', // value keeps everything after the first '='
        ], $result);
    }

    public function testParseContextThrowsWhenPairHasNoEquals(): void
    {
        $this->expectException(RuntimeException::class);
        $this->invokeProtected($this->command(), 'parseContext', [['noequals']]);
    }

    public function testParseContextEmptyInputReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->invokeProtected($this->command(), 'parseContext', [[]]));
    }

    public function testResolveOnErrorPrefersIndexHandler(): void
    {
        // Config is set (non-null), yet the Index's own handler must win.
        config(['elastickit.rebuild.on_error' => static fn () => null]);

        $result = $this->invokeProtected($this->command(), 'resolveOnError', [new HandlerIndex()]);

        // HandlerIndex returns [object, 'handleImportError'] (an array callable),
        // distinct from the config Closure — so the Index's handler was used.
        $this->assertIsArray($result);
    }

    public function testResolveOnErrorFallsBackToConfig(): void
    {
        $handler = static fn () => null;
        config(['elastickit.rebuild.on_error' => $handler]);

        $result = $this->invokeProtected($this->command(), 'resolveOnError', [new PlainIndex()]);

        $this->assertSame($handler, $result);
    }

    public function testResolveOnErrorNullWhenNeitherSet(): void
    {
        config(['elastickit.rebuild.on_error' => null]);

        $this->assertNull($this->invokeProtected($this->command(), 'resolveOnError', [new PlainIndex()]));
    }

    public function testRebuildCommandIsReplacedViaConfig(): void
    {
        $command = Artisan::all()['elastickit:rebuild'] ?? null;

        $this->assertNotNull($command);
        $this->assertInstanceOf(StubRebuildCommand::class, $command);
    }

    public function testContainerCallableResolvesInvokableClassString(): void
    {
        $result = $this->invokeProtected($this->command(), 'containerCallable', [InvokableHandler::class]);

        $this->assertInstanceOf(InvokableHandler::class, $result);
    }

    public function testContainerCallableResolvesClassMethodArray(): void
    {
        $result = $this->invokeProtected($this->command(), 'containerCallable', [
            [HandlerIndex::class, 'handleImportError'],
        ]);

        $this->assertIsArray($result);
        $this->assertInstanceOf(HandlerIndex::class, $result[0]);
        $this->assertSame('handleImportError', $result[1]);
    }

    public function testContainerCallablePassesClosureThrough(): void
    {
        $closure = static fn () => null;

        $this->assertSame(
            $closure,
            $this->invokeProtected($this->command(), 'containerCallable', [$closure])
        );
    }

    private function command(): RebuildCommand
    {
        return new RebuildCommand(app(IndexResolver::class));
    }

    /**
     * Invoke a protected method on an object for testing.
     */
    private function invokeProtected(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }
}

class HandlerIndex extends Index implements HasRebuildErrorHandler
{
    protected string $name = 'handler';

    public function rebuildErrorHandler(): callable
    {
        return [$this, 'handleImportError'];
    }

    public function handleImportError(array $response, array $actions, Bulk $newbulk): void
    {
        // per-index error handler
    }
}

class PlainIndex extends Index
{
    protected string $name = 'plain';
}

class StubRebuildCommand extends RebuildCommand
{
    // marker subclass — exercises config('elastickit.rebuild.command')
}

class InvokableHandler
{
    public function __invoke(array $response, array $actions, Bulk $newbulk): void
    {
        // invokable error handler
    }
}
