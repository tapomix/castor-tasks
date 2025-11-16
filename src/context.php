<?php

namespace tapomix\castor;

use Castor\Attribute\AsContext;
use Castor\Context;
use Symfony\Component\Process\Process;

define('TAPOMIX_DEFAULT_CONTEXT', 'tapomix_default');

#[AsContext(name: TAPOMIX_DEFAULT_CONTEXT, default: true)]
function default_context(): Context
{
    $context = new Context();

    $context
        ->withAllowFailure()
    ;

    if (Process::isTtySupported()) {
        $context = $context->withTTY();
    }

    return $context;
}
