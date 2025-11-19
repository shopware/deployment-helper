<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct;

enum OneTimeTaskWhen: string
{
    case FIRST = 'first';
    case LAST = 'last';
}
