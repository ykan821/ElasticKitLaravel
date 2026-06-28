<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Manager;
use Throwable;

class IndexCreateCommand extends IndexCommand
{
    protected $signature = 'elastickit:index:create
        {index : index alias (from config) or Index class FQCN}';

    protected $description = 'Create the index with its mappings and settings (a real index, not an alias).';

    public function handle(): int
    {
        $index = $this->resolveIndex();

        try {
            (new Manager($index))->create();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Created index {$index->name()}.");
        $this->warn(
            'Created a real index. If you plan to use zero-downtime rebuilds, '
            . 'run elastickit:rebuild instead — it bootstraps via an alias.'
        );

        return self::SUCCESS;
    }
}
