<?php

declare(strict_types=1);

namespace Tapomix\Castor\Infra;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;

use function Castor\context;
use function Castor\fs;
use function Castor\io;
use function Castor\run;

define('TAPOMIX_NAMESPACE_INFRA', 'tapomix-infra');

/* Frozen files list, defined by the template and synced to derived projects. */
define('TAPOMIX_FROZEN_FILES_SYNC_NAME', '.castor/git-frozen-sync.php');
define('TAPOMIX_FROZEN_FILES_SYNC_PATH', PathHelper::getRoot() . '/' . TAPOMIX_FROZEN_FILES_SYNC_NAME);

/* Path to the exclusion list, defined by the derived project to opt out of specific frozen files. */
define('TAPOMIX_FROZEN_FILES_EXCLUDE_PATH', PathHelper::getRoot() . '/.castor/git-frozen-exclude.php');

#[AsTask(namespace: TAPOMIX_NAMESPACE_INFRA, description: 'Sync frozen files from the infra remote', aliases: ['infra:sync'])]
function sync(
    #[AsOption(shortcut: 'r', description: 'Infra remote name')]
    string $remote = 'infra',
    #[AsOption(shortcut: 'b', description: 'Branch to sync from')]
    string $branch = 'main',
): void {
    io()->title(\sprintf('Syncing frozen files from remote "%s"', $remote));

    if (!fs()->exists(TAPOMIX_FROZEN_FILES_SYNC_PATH)) {
        io()->error(\sprintf('Frozen files list not found at "%s".', TAPOMIX_FROZEN_FILES_SYNC_PATH));

        return;
    }

    /** @var string[] $excludedFiles */
    $excludedFiles = fs()->exists(TAPOMIX_FROZEN_FILES_EXCLUDE_PATH) ? require TAPOMIX_FROZEN_FILES_EXCLUDE_PATH : [];

    // check that the remote exists
    $process = run(['git', 'remote'], context()->withQuiet());
    $remotes = \array_filter(\explode("\n", \trim($process->getOutput())));
    if (!\in_array($remote, $remotes, true)) {
        io()->error(\sprintf(
            'Remote "%s" not found. Add it with: git remote add %s <url>',
            $remote,
            $remote,
        ));

        return;
    }

    io()->text(\sprintf('Fetching from remote "%s"...', $remote));
    if (!run(['git', 'fetch', $remote])->isSuccessful()) {
        io()->error(\sprintf('Unable to fetch from remote "%s".', $remote));

        return;
    }

    // The list is itself frozen, so restore it before reading it: a file dropped by the template
    // but still named in the local list makes git reject the whole pathspec, leaving every other
    // frozen file untouched. Reading the remote list first keeps the sync self-healing.
    if (!\in_array(TAPOMIX_FROZEN_FILES_SYNC_NAME, $excludedFiles, true)) {
        io()->text('Updating the frozen files list...');

        if (!checkoutFiles($remote, $branch, [TAPOMIX_FROZEN_FILES_SYNC_NAME])) {
            io()->warning(\sprintf(
                'No frozen files list on "%s/%s", falling back to the local one.',
                $remote,
                $branch,
            ));
        }
    }

    /** @var string[] $frozenFiles */
    $frozenFiles = require TAPOMIX_FROZEN_FILES_SYNC_PATH;

    $filesToSync = computeFilesToSync($frozenFiles, $excludedFiles);

    if ([] === $filesToSync) {
        io()->warning('No files to sync after applying exclusions.');

        return;
    }

    io()->text('Restoring frozen files...');
    if (!checkoutFiles($remote, $branch, $filesToSync)) {
        io()->error(\sprintf('Unable to restore the frozen files from "%s/%s".', $remote, $branch));

        return;
    }

    io()->success('Frozen files updated.');
}

/**
 * Checkout files from a remote branch.
 *
 * @param string[] $files
 */
function checkoutFiles(string $remote, string $branch, array $files): bool
{
    return run(['git', 'checkout', \sprintf('%s/%s', $remote, $branch), '--', ...$files])->isSuccessful();
}

/**
 * Compute the final list of files to sync by applying exclusions.
 *
 * @param string[] $frozen   Files declared as frozen by the infra remote
 * @param string[] $excluded Files opted out by the derived project
 *
 * @return string[]
 */
function computeFilesToSync(array $frozen, array $excluded): array
{
    return \array_values(\array_diff($frozen, $excluded));
}
