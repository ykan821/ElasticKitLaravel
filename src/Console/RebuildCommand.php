<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Rebuild;
use Throwable;

class RebuildCommand extends IndexCommand
{
    protected $signature = 'elastickit:rebuild
        {index : index alias (from config) or Index class FQCN}
        {--batch-size= : bulk batch size (default 1000)}
        {--allow-empty : allow importing zero documents}
        {--clean : delete the previous backing index after the swap}';

    protected $description = 'Zero-downtime rebuild: create a backing index, import via Index::source(), and swap the alias.';

    public function handle(): int
    {
        $index = $this->resolveIndex();

        $rebuild = (new Rebuild($index))
            ->batchSize((int) ($this->option('batch-size') ?: 1000));
        if ($this->option('allow-empty')) {
            $rebuild->allowEmpty();
        }

        try {
            $result = $rebuild->run();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Rebuilt index {$index->name()}.");
        $this->line("  new backing index: <comment>{$result['newIndex']}</comment>");
        if (!empty($result['oldIndex'])) {
            $this->line("  previous backing index: <comment>{$result['oldIndex']}</comment>");

            if ($this->option('clean')) {
                try {
                    (new Rebuild($index))->clean($result['oldIndex']);
                    $this->line("  cleaned previous backing index: <comment>{$result['oldIndex']}</comment>");
                } catch (Throwable $e) {
                    $this->warn("  failed to clean previous backing index {$result['oldIndex']}: {$e->getMessage()}");
                }
            }
        }

        return self::SUCCESS;
    }
}
