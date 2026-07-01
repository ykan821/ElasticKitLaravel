<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Tests;

use Elastic\Elasticsearch\ClientInterface;
use Elastic\Transport\Transport;
use ElasticKit\Laravel\LazyClient;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Unit-tests LazyClient without booting the provider or touching the network.
 */
class LazyClientTest extends TestCase
{
    public function testFactoryInvokedOnce(): void
    {
        $calls = 0;
        $client = new DummyClient();

        $lazy = new LazyClient('default', function () use (&$calls, $client) {
            $calls++;

            return $client;
        });

        $lazy->getAsync();
        $lazy->getAsync();

        $this->assertSame(1, $calls);
    }

    public function testFactoryFailureWrappedWithConnectionName(): void
    {
        $cause = new InvalidArgumentException('bad cloud_id');
        $lazy = new LazyClient('cluster_a', fn () => throw $cause);

        try {
            $lazy->getAsync();
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("connection 'cluster_a'", $e->getMessage());
            $this->assertStringContainsString('bad cloud_id', $e->getMessage());
            $this->assertSame($cause, $e->getPrevious());
        }
    }

    public function testForwardsInterfaceMethod(): void
    {
        $client = new DummyClient();
        $client->async = true;

        $lazy = new LazyClient('default', fn () => $client);

        $this->assertTrue($lazy->getAsync());
    }

    public function testSettersReturnProxyForChaining(): void
    {
        $client = new DummyClient();
        $lazy = new LazyClient('default', fn () => $client);

        $this->assertSame($lazy, $lazy->setAsync(true));
        $this->assertTrue($client->async);
    }

    public function testForwardsMagicCall(): void
    {
        $client = new DummyClient();
        $lazy = new LazyClient('default', fn () => $client);

        $this->assertSame('echo:hi', $lazy->customMethod('hi'));
        $this->assertSame('hi', $client->customArg);
    }
}

/**
 * Minimal ClientInterface stub. Only the async/meta/response flags are live;
 * transport/logger/sendRequest are out of scope and throw if reached.
 *
 * customMethod() is outside ClientInterface — it exercises LazyClient::__call.
 */
class DummyClient implements ClientInterface
{
    public bool $async = false;
    public bool $metaHeader = false;
    public bool $responseException = true;
    public ?string $customArg = null;

    public function getTransport(): Transport
    {
        throw new RuntimeException('not used in test');
    }

    public function getLogger(): LoggerInterface
    {
        throw new RuntimeException('not used in test');
    }

    public function setAsync(bool $async): ClientInterface
    {
        $this->async = $async;

        return $this;
    }

    public function getAsync(): bool
    {
        return $this->async;
    }

    public function setElasticMetaHeader(bool $active): ClientInterface
    {
        $this->metaHeader = $active;

        return $this;
    }

    public function getElasticMetaHeader(): bool
    {
        return $this->metaHeader;
    }

    public function setResponseException(bool $active): ClientInterface
    {
        $this->responseException = $active;

        return $this;
    }

    public function getResponseException(): bool
    {
        return $this->responseException;
    }

    public function sendRequest(RequestInterface $request)
    {
        throw new RuntimeException('not used in test');
    }

    public function customMethod(string $arg): string
    {
        $this->customArg = $arg;

        return 'echo:' . $arg;
    }
}
