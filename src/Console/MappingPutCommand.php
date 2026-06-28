<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Manager;
use Throwable;

class MappingPutCommand extends IndexCommand
{
    protected $signature = 'elastickit:mapping:put
        {index : index alias (from config) or Index class FQCN}';

    protected $description = 'Push the Index mappings to the existing index.';

    public function handle(): int
    {
        $index = $this->resolveIndex();

        try {
            (new Manager($index))->putMapping();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Updated mappings for {$index->name()}.");

        return self::SUCCESS;
    }
}
