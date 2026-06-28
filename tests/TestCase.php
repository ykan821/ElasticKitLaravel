<?php

declare(strict_types=1);

namespace ElasticKit\Laravel\Tests;

use ElasticKit\Index\Support\ClientManager;
use ElasticKit\Index\Support\Pagination;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

/**
 * Base test case: resets ElasticKit's process-global static state before boot
 * and after each test, so tests don't depend on run order.
 */
abstract class TestCase extends TestbenchTestCase
{
    protected function setUp(): void
    {
        ClientManager::reset();
        Pagination::reset();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        ClientManager::reset();
        Pagination::reset();
        parent::tearDown();
    }
}
