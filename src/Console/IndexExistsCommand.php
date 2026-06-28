<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Manager;
use Throwable;

class IndexExistsCommand extends IndexCommand
{
    protected $signature = 'elastickit:index:exists
        {index : index alias (from config) or Index class FQCN}';

    protected $description = 'Check whether the index exists (exit code 0 = exists, 1 = missing).';

    public function handle(): int
    {
        $index = $this->resolveIndex();

        try {
            $exists = (new Manager($index))->exists();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $exists
            ? $this->info("Index {$index->name()} exists.")
            : $this->warn("Index {$index->name()} does not exist.");

        return $exists ? self::SUCCESS : self::FAILURE;
    }
}
