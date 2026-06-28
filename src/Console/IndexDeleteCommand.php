<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Manager;
use Throwable;

class IndexDeleteCommand extends IndexCommand
{
    protected $signature = 'elastickit:index:delete
        {index : index alias (from config) or Index class FQCN}
        {--force : skip the confirmation prompt}';

    protected $description = 'Delete the index (resolving an alias to its backing index).';

    public function handle(): int
    {
        $index = $this->resolveIndex();

        if (!$this->option('force') && !$this->confirm("Delete index {$index->name()}?", false)) {
            $this->warn('Cancelled.');
            return self::SUCCESS;
        }

        try {
            // resolveAlias=true so this works after a zero-downtime rebuild,
            // where name() is an alias pointing at a backing index.
            (new Manager($index))->delete(true);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Deleted index {$index->name()}.");

        return self::SUCCESS;
    }
}
