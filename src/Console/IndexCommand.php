<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Index;
use Illuminate\Console\Command;

/**
 * Base for commands that operate on a single {index} argument.
 */
abstract class IndexCommand extends Command
{
    public function __construct(protected IndexResolver $resolver)
    {
        parent::__construct();
    }

    /**
     * Resolve the {index} argument into an Index instance.
     */
    protected function resolveIndex(): Index
    {
        return $this->resolver->resolve((string) $this->argument('index'));
    }
}
