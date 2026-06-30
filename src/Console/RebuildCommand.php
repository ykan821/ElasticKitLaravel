<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Index;
use ElasticKit\Index\Rebuild;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Throwable;

class RebuildCommand extends IndexCommand
{
    protected $signature = 'elastickit:rebuild
        {index : index alias (from config) or Index class FQCN}
        {--batch-size= : bulk batch size (default 1000)}
        {--allow-empty : allow importing zero documents}
        {--clean : delete the previous backing index after the swap}
        {--context=* : source context as key=value pairs (repeatable)}';

    protected $description = 'Zero-downtime rebuild: create a backing index, import via Index::source(), and swap the alias.';

    public function handle(): int
    {
        $index = $this->resolveIndex();
        $name = $index->name();

        $indicator = $this->output->isDecorated()
            ? new ProgressIndicator($this->output->getOutput())
            : null;
        $count = 0;

        $rebuild = (new Rebuild($index))
            ->batchSize((int) ($this->option('batch-size') ?: 1000));

        // Always wrap Index::source() to count imported docs (context passes through,
        // core stays untouched). The spinner renders only on a TTY; non-interactive
        // runs still get the count in the summary, with no extra log output.
        $rebuild->source(function (array $context = []) use ($index, $indicator, $name, &$count) {
            $lastPaint = 0.0;
            foreach ($index->source($context) as $id => $doc) {
                $count++;
                // Throttle repaints to ~10Hz: a source can yield far faster than the
                // eye can follow, and unchecked repaints flicker.
                if ($indicator !== null) {
                    $now = microtime(true);
                    if ($now - $lastPaint >= 0.1) {
                        $indicator->setMessage("rebuilding {$name} · imported " . number_format($count) . ' docs');
                        $indicator->advance();
                        $lastPaint = $now;
                    }
                }
                yield $id => $doc;
            }
        });

        if ($this->option('allow-empty')) {
            $rebuild->allowEmpty();
        }

        $onError = $this->resolveOnError($index);
        if ($onError !== null) {
            $rebuild->onError($this->containerCallable($onError));
        }

        $indicator?->start("rebuilding {$name}");

        try {
            $context = $this->parseContext((array) $this->option('context'));
            $result = $rebuild->run($context);
        } catch (Throwable $e) {
            $this->stopIndicator($indicator);
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->stopIndicator($indicator);

        $this->info("Rebuilt index {$name}.");
        $this->line("  imported <comment>" . number_format($count) . '</comment> docs');
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

    /**
     * Clear the spinner and leave the cursor on a fresh line. No-op when running
     * non-interactive, since the spinner was never created.
     */
    protected function stopIndicator(?ProgressIndicator $indicator): void
    {
        if ($indicator === null) {
            return;
        }

        $indicator->finish('');
    }

    /**
     * Parse repeatable --context=key=value pairs into an associative array.
     * Values are kept as strings; cast inside Index::source() if needed.
     *
     * @param array<int, string> $pairs
     * @return array<string, string>
     */
    protected function parseContext(array $pairs): array
    {
        $context = [];
        foreach ($pairs as $pair) {
            if (!str_contains($pair, '=')) {
                throw new RuntimeException("--context expects key=value, got: {$pair}");
            }
            [$key, $value] = explode('=', $pair, 2);
            $context[trim($key)] = $value;
        }
        return $context;
    }

    /**
     * Resolve the rebuild onError handler: an Index's own declaration
     * (HasRebuildErrorHandler) wins over the global config; null aborts.
     */
    protected function resolveOnError(Index $index): ?callable
    {
        return $index instanceof HasRebuildErrorHandler
            ? $index->rebuildErrorHandler()
            : config('elastickit.rebuild.on_error');
    }

    /**
     * Resolve a handler spec into a callable via the container, so constructor
     * dependency injection works uniformly — invokable class-string,
     * [class-string, method], or already a Closure / [object, method].
     */
    protected function containerCallable(string|array|object $handler): callable
    {
        if (is_string($handler)) {
            return app($handler);
        }
        if (is_array($handler) && isset($handler[0]) && is_string($handler[0])) {
            return [app($handler[0]), $handler[1]];
        }
        return $handler;
    }
}
