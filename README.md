# ElasticKit Laravel

Laravel integration for [ElasticKit](https://github.com/ykan821/ElasticKit) — wires up the Elasticsearch
client from config and bridges ElasticKit's pagination to Laravel's native paginator.

## Installation

Requires PHP 8.1+, Laravel 10.x–12.x, and Elasticsearch 8.x.

```
composer require ykan/elastickit-laravel
```

ElasticKit (the framework-agnostic core) is pulled in automatically as a dependency of this
package — you require only this one. The service provider is **auto-discovered**; no manual
registration is needed.

Publish the config:

```
php artisan vendor:publish --tag=elastickit-config
```

Then set your cluster credentials (`.env`):

```
ELASTICKIT_HOST=http://localhost:9200
# Elastic Cloud / serverless:
# ELASTICKIT_CLOUD_ID=...
# ELASTICKIT_API_KEY=...
# ELASTICKIT_API_KEY_ID=...
# Traditional secured cluster:
# ELASTICKIT_USERNAME=elastic
# ELASTICKIT_PASSWORD=...
```

## What it does

- Builds the Elasticsearch client from `config('elastickit.connections')` and registers each via
  `Index::setClient($client, $name)` (multi-connection supported).
- Registers a **page resolver**, so `paginate()` with no arguments reads `?page` / `?per_page` from
  the request automatically (`perPage` is still capped by each index's `maxPerPage()`).
- Registers a **paginator resolver**, so `Results::toPaginator()` returns a Laravel
  `LengthAwarePaginator`.

## Usage

```php
use ElasticKit\Index\Index;

class ProductIndex extends Index
{
    protected string $name = 'products';
}

// compound bool query, paginated → native Laravel LengthAwarePaginator
$results = ProductIndex::query()
    ->bool([
        'must'   => fn ($q) => $q->match('title', 'shoes'),
        'filter' => fn ($q) => $q
            ->range('price', [10, 100])
            ->when($status, fn ($q) => $q->term('status', $status))  // conditional clause
            ->term('status', 'published'),
    ])
    ->highlight('title')
    ->sort('price', 'asc')
    ->paginate($page, $perPage);   // or ->paginate() to read from the request

$results->total();                 // hit count
$results->toPaginator()->links();  // native Laravel pagination UI
```

## Artisan commands

Pass an Index class FQCN, or optionally map short aliases in `config/elastickit.php`:

```php
'indices' => [
    'products' => \App\Search\ProductIndex::class,
],
```

**Index management**

| Command | Description | Options |
|---|---|---|
| `php artisan elastickit:index:create products` | Create the index with its mappings | — |
| `php artisan elastickit:mapping:put products` | Push mapping changes to an existing index | — |
| `php artisan elastickit:index:exists products` | Check existence — exit code 0 = exists | — |
| `php artisan elastickit:index:delete products` | Delete the index | `--force` |

**Zero-downtime rebuild**

| Command | Description | Options |
|---|---|---|
| `php artisan elastickit:rebuild products` | Build a new backing index, import via `Index::source()`, swap the alias | `--clean`, `--batch-size=`, `--allow-empty`, `--context=` |
| `php artisan elastickit:rebuild:rollback products <backing>` | Point the alias back at a previous backing index | — |
| `php artisan elastickit:rebuild:clean products <backing>` | Delete a leftover backing index | `--force` |
| `php artisan elastickit:rebuild:unlock products` | Release a lock left by a crashed rebuild | — |

`--clean` removes the previous backing index right after the swap.

**Passing context to `source()`**

`--context=key=value` (repeatable) is forwarded to `Index::source()` as an associative array —
handy for incremental or filtered rebuilds. Values are strings; cast inside `source()`:

```
php artisan elastickit:rebuild products --context=since=2026-06-01 --context=limit=1000
```

```php
public function source(array $context = []): iterable
{
    $query = Product::query();
    if ($since = $context['since'] ?? null) {
        $query->where('updated_at', '>', $since);
    }
    if ($limit = $context['limit'] ?? null) {
        $query->limit((int) $limit);
    }
    foreach ($query->cursor() as $p) {
        yield $p->id => $p->toArray();
    }
}
```

> Prefer `elastickit:rebuild` for first-time setup if you ever want zero-downtime
> rebuilds: it bootstraps via an alias, while `index:create` makes a plain index that
> `rebuild` cannot swap.

**Handling import errors**

By default, a bulk import error aborts the rebuild (the new backing index is deleted). To
customize — skip failed items, log them, or retry — set `rebuild.on_error` in the published
config to an invokable class:

```php
// config/elastickit.php
'rebuild' => [
    'on_error' => \App\Search\ImportErrorHandler::class,
],
```

```php
// app/Search/ImportErrorHandler.php
use ElasticKit\Index\Bulk;

class ImportErrorHandler
{
    public function __invoke(array $response, array $actions, Bulk $newbulk): void
    {
        foreach ($response['items'] ?? [] as $item) {
            if (isset($item['index']['error'])) {
                logger('elastickit import failed', ['id' => $item['index']['_id'] ?? null]);
            }
        }
        // return → drop the failed items and continue
        // throw  → abort the rebuild
        // re-send on $newbulk, then $newbulk->flush() → retry
    }
}
```

**Per-index handlers**

For index-specific error handling, implement `HasRebuildErrorHandler` on the Index subclass —
it overrides the global `rebuild.on_error` for that index only:

```php
use ElasticKit\Index\Bulk;
use ElasticKit\Index\Index;
use ElasticKit\Laravel\Console\HasRebuildErrorHandler;

class ProductIndex extends Index implements HasRebuildErrorHandler
{
    protected string $name = 'products';

    public function rebuildErrorHandler(): callable
    {
        return function (array $response, array $actions, Bulk $newbulk): void {
            // per-index logic; same contract as the global handler
        };
    }
}
```

**Replacing the rebuild command**

To take over the `elastickit:rebuild` name entirely (custom `handle()`, extra options, etc),
set `rebuild.command` to a `RebuildCommand` subclass — the provider registers yours instead of
its default:

```php
// config/elastickit.php
'rebuild' => [
    'command' => \App\Console\Commands\CustomRebuildCommand::class,
],
```

```php
use ElasticKit\Laravel\Console\RebuildCommand;

class CustomRebuildCommand extends RebuildCommand
{
    // inherit the elastickit:rebuild signature; override handle().
    // stopIndicator() and parseContext() are protected — reuse them from your handle().
}
```

## Configuration

See `config/elastickit.php` after publishing. Define additional connections under `connections`
and target one from an Index subclass:

```php
class ArchiveIndex extends Index
{
    protected string $name = 'archive';
    protected string $connection = 'archive';
}
```

## Todo

- [ ] Documentation sync — Eloquent auto-sync (`IndexesTo`) model trait.

## License

MIT
