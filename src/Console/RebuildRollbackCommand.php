<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Rebuild;
use Throwable;

class RebuildRollbackCommand extends IndexCommand
{
    protected $signature = 'elastickit:rebuild:rollback
        {index : index alias (resolves the connection)}
        {target : the backing index name to roll back to}';

    protected $description = 'Roll the alias back to a previous backing index.';

    public function handle(): int
    {
        $index = $this->resolveIndex();
        $target = (string) $this->argument('target');

        try {
            $restored = (new Rebuild($index))->rollback($target);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Rolled back {$index->name()} to backing index {$restored}.");

        return self::SUCCESS;
    }
}
