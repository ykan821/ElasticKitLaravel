<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Index;
use RuntimeException;

/**
 * Resolves an index alias (from config) or an Index class FQCN into an instance.
 */
class IndexResolver
{
    /**
     * @return Index
     * @throws RuntimeException when the alias/class is not a registered ElasticKit Index
     */
    public function resolve(string $aliasOrClass): Index
    {
        $class = (string) config('elastickit.indices.' . $aliasOrClass, $aliasOrClass);

        if (!class_exists($class) || !is_subclass_of($class, Index::class)) {
            throw new RuntimeException("Unknown ElasticKit index: {$aliasOrClass}");
        }

        return new $class();
    }
}
