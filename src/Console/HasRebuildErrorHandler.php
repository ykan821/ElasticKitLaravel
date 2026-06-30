<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Console;

use ElasticKit\Index\Bulk;

/**
 * Implemented by Index subclasses that want their own rebuild error handler,
 * overriding the global elastickit.rebuild.on_error config for that index.
 *
 * The handler is forwarded to Rebuild::onError() and receives the raw bulk
 * response, the batch actions, and a fresh Bulk bound to the new backing index.
 */
interface HasRebuildErrorHandler
{
    /**
     * @return callable function (array $response, array $actions, Bulk $newbulk): void
     *                  Return to drop the failed items and continue, throw to abort,
     *                  or re-import on $newbulk then flush() to retry.
     */
    public function rebuildErrorHandler(): callable;
}
