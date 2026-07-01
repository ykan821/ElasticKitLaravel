<?php

declare(strict_types=1);

namespace ElasticKit\Laravel;

use Elastic\Elasticsearch\ClientInterface;
use Elastic\Transport\Transport;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Defers Elasticsearch client construction until first use.
 *
 * Registered by the provider in place of an eagerly built client so a
 * misconfigured connection cannot crash application boot. The client is built
 * on the first method call; a build failure surfaces as a RuntimeException
 * naming the connection rather than a raw ClientBuilder trace.
 */
final class LazyClient implements ClientInterface
{
    private ?ClientInterface $client = null;

    /**
     * @param string   $connection connection name, surfaced in error messages
     * @param \Closure $factory    builds the real client on demand
     */
    public function __construct(
        private readonly string $connection,
        private readonly \Closure $factory,
    ) {
    }

    public function getTransport(): Transport
    {
        return $this->client()->getTransport();
    }

    public function getLogger(): LoggerInterface
    {
        return $this->client()->getLogger();
    }

    public function setAsync(bool $async): ClientInterface
    {
        $this->client()->setAsync($async);

        return $this;
    }

    public function getAsync(): bool
    {
        return $this->client()->getAsync();
    }

    public function setElasticMetaHeader(bool $active): ClientInterface
    {
        $this->client()->setElasticMetaHeader($active);

        return $this;
    }

    public function getElasticMetaHeader(): bool
    {
        return $this->client()->getElasticMetaHeader();
    }

    public function setResponseException(bool $active): ClientInterface
    {
        $this->client()->setResponseException($active);

        return $this;
    }

    public function getResponseException(): bool
    {
        return $this->client()->getResponseException();
    }

    public function sendRequest(RequestInterface $request)
    {
        return $this->client()->sendRequest($request);
    }

    /**
     * Forward non-interface methods (indices(), search(), get(), …) to the real
     * client. Client provides these via traits, so they are undeclared here and
     * reach this magic accessor.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->client()->$name(...$arguments);
    }

    private function client(): ClientInterface
    {
        if ($this->client === null) {
            try {
                $this->client = ($this->factory)();
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Failed to build Elasticsearch client for connection '{$this->connection}': "
                    . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return $this->client;
    }
}
