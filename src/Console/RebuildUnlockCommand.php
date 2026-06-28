<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Rebuild;
use Throwable;

class RebuildUnlockCommand extends IndexCommand
{
    protected $signature = 'elastickit:rebuild:unlock
        {index : index alias (from config) or Index class FQCN}';

    protected $description = 'Force-release a rebuild lock left behind by a crashed run.';

    public function handle(): int
    {
        $index = $this->resolveIndex();

        try {
            (new Rebuild($index))->forceUnlock();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Released rebuild lock for {$index->name()}.");

        return self::SUCCESS;
    }
}
