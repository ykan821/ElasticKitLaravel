<?php

declare(strict_types=1);

namespace ElasticKit\Laravel;

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\ClientInterface;
use ElasticKit\Index\Index;
use ElasticKit\Index\Support\Pagination;
use ElasticKit\Laravel\Console\IndexCreateCommand;
use ElasticKit\Laravel\Console\IndexDeleteCommand;
use ElasticKit\Laravel\Console\IndexExistsCommand;
use ElasticKit\Laravel\Console\IndexResolver;
use ElasticKit\Laravel\Console\MappingPutCommand;
use ElasticKit\Laravel\Console\RebuildCleanCommand;
use ElasticKit\Laravel\Console\RebuildCommand;
use ElasticKit\Laravel\Console\RebuildRollbackCommand;
use ElasticKit\Laravel\Console\RebuildUnlockCommand;
use ElasticKit\Laravel\Pagination\LaravelPagination;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Bootstraps ElasticKit inside a Laravel app: registers one Elasticsearch
 * client per configured connection and wires pagination to Laravel's paginator.
 */
class ElasticKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/elastickit.php', 'elastickit');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__ . '/../config/elastickit.php' => config_path('elastickit.php')],
                'elastickit-config'
            );

            // elastickit:rebuild may be overridden via config (a RebuildCommand
            // subclass), so a custom command occupies the name instead of the default.
            $this->commands([
                config('elastickit.rebuild.command') ?: RebuildCommand::class,
                RebuildCleanCommand::class,
                RebuildRollbackCommand::class,
                RebuildUnlockCommand::class,
                IndexCreateCommand::class,
                IndexDeleteCommand::class,
                IndexExistsCommand::class,
                MappingPutCommand::class,
            ]);
        }

        $this->app->singleton(IndexResolver::class);

        $this->registerClients();
        $this->registerPaginationResolvers();
    }

    /**
     * Build and register an Elasticsearch client for each configured connection.
     */
    private function registerClients(): void
    {
        $connections = (array) config('elastickit.connections', []);

        if ($connections === []) {
            throw new RuntimeException(
                'No elastickit connections configured. '
                . 'Define at least a "default" entry in config("elastickit.connections").'
            );
        }

        foreach ($connections as $name => $cfg) {
            $cfg = (array) $cfg;
            $name = (string) $name;
            Index::setClient(
                new LazyClient($name, fn () => $this->buildClient($cfg)),
                $name
            );
        }
    }

    /**
     * Construct an Elasticsearch client from a connection config block.
     *
     * @param array<string, mixed> $cfg
     */
    private function buildClient(array $cfg): ClientInterface
    {
        $builder = ClientBuilder::create();

        // Cloud ID fully determines the endpoint; hosts are derived from it.
        if (!empty($cfg['cloud_id'])) {
            $builder->setElasticCloudId((string) $cfg['cloud_id']);
        } else {
            $builder->setHosts((array) ($cfg['hosts'] ?? ['http://localhost:9200']));
        }

        if (!empty($cfg['api_key'])) {
            $builder->setApiKey(
                (string) $cfg['api_key'],
                !empty($cfg['api_key_id']) ? (string) $cfg['api_key_id'] : null
            );
        }

        if (!empty($cfg['username']) && !empty($cfg['password'])) {
            $builder->setBasicAuthentication((string) $cfg['username'], (string) $cfg['password']);
        }

        if (isset($cfg['retries'])) {
            $builder->setRetries((int) $cfg['retries']);
        }

        if (array_key_exists('ssl_verification', $cfg)) {
            $builder->setSSLVerification((bool) $cfg['ssl_verification']);
        }

        return $builder->build();
    }

    private function registerPaginationResolvers(): void
    {
        $paginator = (array) config('elastickit.paginator', []);
        $pageName = (string) ($paginator['page_name'] ?? 'page');

        Pagination::setPageResolver(
            LaravelPagination::pageResolver(
                (int) ($paginator['default_per_page'] ?? 15),
                $pageName
            )
        );

        Pagination::setPaginatorResolver(
            LaravelPagination::paginatorResolver($pageName)
        );
    }
}
