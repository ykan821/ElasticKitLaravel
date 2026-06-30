# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- `elastickit:rebuild` shows a spinner during import (TTY only) and reports the imported-doc count on completion.
- Rebuild `onError` handler can be set via the `elastickit.rebuild.on_error` config key (invokable class-string or callable) — no command subclassing needed.
- Per-index rebuild error handlers: an Index subclass implementing `HasRebuildErrorHandler` declares its own handler, overriding the global config for that index.
- `elastickit:rebuild` accepts repeatable `--context=key=value` options, forwarded to `Index::source()` via `Rebuild::run($context)`.
- The `elastickit:rebuild` command can be replaced via the `elastickit.rebuild.command` config key (a `RebuildCommand` subclass); the provider registers it instead of the default, so a custom command occupies the same name.
- `RebuildCommand`'s `stopIndicator()` and `parseContext()` are now `protected` (were `private`) — non-BC widening, so subclasses overriding `handle()` reuse the built-in spinner teardown and context parsing.

## [v1.0.0-beta.1] - 2026-06-30

### Added
- Service provider (auto-discovered): bootstraps Elasticsearch clients from
  `config('elastickit.connections')` — multi-connection, with Cloud ID / API key /
  basic-auth / SSL options.
- Pagination bridge: registers page + paginator resolvers so
  `Results::toPaginator()` returns a Laravel `LengthAwarePaginator`, and `paginate()`
  with no arguments reads `?page` / `?per_page` from the request.
- Publishable config (`config/elastickit.php`) with `indices` alias map.
- Artisan commands:
  - `elastickit:rebuild` (zero-downtime rebuild; flags `--clean`, `--batch-size`, `--allow-empty`)
  - `elastickit:rebuild:clean`, `elastickit:rebuild:rollback`, `elastickit:rebuild:unlock`
  - `elastickit:index:create`, `elastickit:index:delete` (`--force`), `elastickit:index:exists`
  - `elastickit:mapping:put`
- Index resolution by config alias or class FQCN.

### Changed
- CI now runs the test suite across a Laravel 10 / 11 / 12 × PHP 8.1–8.3 matrix
  (previously PHP-only). Dev constraints for `orchestra/testbench` and
  `phpunit/phpunit` were broadened to cover the full matrix.
