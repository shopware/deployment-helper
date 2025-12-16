<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct;

/**
 * Defines when a one-time task should be executed during deployment.
 *
 * - BEFORE: Execute before the Shopware update (system:update) is run
 * - AFTER: Execute after the Shopware update is complete (default)
 */
enum OneTimeTaskWhen: string
{
    case BEFORE = 'before';
    case AFTER = 'after';
}
