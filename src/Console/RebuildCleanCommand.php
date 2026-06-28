<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Rebuild;
use Throwable;

class RebuildCleanCommand extends IndexCommand
{
    protected $signature = 'elastickit:rebuild:clean
        {index : index alias (resolves the connection)}
        {backing : the concrete backing index name to delete}
        {--force : skip the confirmation prompt}';

    protected $description = 'Delete a backing index left over from a rebuild.';

    public function handle(): int
    {
        $index = $this->resolveIndex();
        $backing = (string) $this->argument('backing');

        if (!$this->option('force') && !$this->confirm("Delete backing index {$backing}?", false)) {
            $this->warn('Cancelled.');
            return self::SUCCESS;
        }

        try {
            (new Rebuild($index))->clean($backing);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Deleted backing index {$backing}.");

        return self::SUCCESS;
    }
}
