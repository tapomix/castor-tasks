<?php

declare(strict_types=1);

namespace Tapomix\Castor\CastorLock;

use Castor\Helper\PathHelper;

use function Castor\context;
use function Castor\io;
use function Castor\run;

/*
 * Castor reinstalls its own dependencies when the lock "content-hash" stops matching
 * .castor/vendor/composer.installed, and that hash covers castor.composer.json alone: a lock bumped
 * under the same constraint (^0.5: 0.5.0 => 0.5.1) leaves the castor vendor silently stale.
 * The tasks that bring a new lock (a pull, a sync) hash it before, then act on a change.
 */

/* Castor own dependency lock: a new one requires reinstalling its vendor. */
define('TAPOMIX_CASTOR_LOCK_PATH', PathHelper::getRoot() . '/.castor/castor.composer.lock');

/* Qualified: the "composer" alias belongs to tapomix-composer in a derived project. */
const INSTALL_COMMAND = ['castor', 'castor:composer', 'install'];

/**
 * Hash a file, or null when it is absent.
 *
 * Deliberately built on is_file() rather than fs(): the helper must stay usable without a booted
 * castor container, and a directory is not a lock.
 */
function hashFile(string $path): ?string
{
    if (!\is_file($path)) {
        return null;
    }

    return \md5_file($path) ?: null;
}

/** Hash of the castor lock, read before the step that may bring a new one. */
function lockHash(): ?string
{
    return hashFile(TAPOMIX_CASTOR_LOCK_PATH);
}

function lockChanged(?string $hashBefore): bool
{
    return $hashBefore !== lockHash();
}

/** Warn when the castor lock changed: for a step whose result is reviewed by hand first (a sync in dev). */
function warnOnLockChange(?string $hashBefore): void
{
    if (!lockChanged($hashBefore)) {
        return;
    }

    io()->warning(\sprintf('The castor lock changed, run "%s" to update its vendor.', \implode(' ', INSTALL_COMMAND)));
}

/**
 * Install the castor tasks when the castor lock changed: for a deployment step (a pull on the VPS).
 *
 * Never blocking: the castor vendor does not affect the app or the service, a failure (network,
 * GitHub) must not stop their update. The run in progress already loaded the previous tasks: the new
 * ones apply from the next run.
 */
function installOnLockChange(?string $hashBefore): void
{
    if (!lockChanged($hashBefore)) {
        return;
    }

    io()->section('Updating the castor tasks');

    if (!run(INSTALL_COMMAND, context()->withAllowFailure())->isSuccessful()) {
        io()->warning(\sprintf('Castor tasks not updated, the update goes on: run "%s" by hand.', \implode(' ', INSTALL_COMMAND)));

        return;
    }

    io()->note('Castor tasks updated: they apply from the next castor run.');
}
