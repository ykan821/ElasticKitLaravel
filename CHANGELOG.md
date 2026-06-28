# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
