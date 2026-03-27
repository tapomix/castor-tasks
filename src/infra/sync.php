<?php

namespace Tapomix\Castor\Infra;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;

use function Castor\context;
use function Castor\fs;
use function Castor\io;
use function Castor\run;

define('TAPOMIX_NAMESPACE_INFRA', 'tapomix-infra');

/* Path to the frozen files list, defined by the template and synced to derived projects. */
define('TAPOMIX_FROZEN_FILES_SYNC_PATH', PathHelper::getRoot() . '/.castor/git-frozen-sync.php');

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

    // load frozen files list
    if (!fs()->exists(TAPOMIX_FROZEN_FILES_SYNC_PATH)) {
        io()->error(\sprintf('Frozen files list not found at "%s".', TAPOMIX_FROZEN_FILES_SYNC_PATH));

        return;
    }

    /** @var string[] $frozenFiles */
    $frozenFiles = require TAPOMIX_FROZEN_FILES_SYNC_PATH;

    /** @var string[] $excludedFiles */
    $excludedFiles = fs()->exists(TAPOMIX_FROZEN_FILES_EXCLUDE_PATH) ? require TAPOMIX_FROZEN_FILES_EXCLUDE_PATH : [];

    $filesToSync = computeFilesToSync($frozenFiles, $excludedFiles);

    if ([] === $filesToSync) {
        io()->warning('No files to sync after applying exclusions.');

        return;
    }

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
    run(['git', 'fetch', $remote]);

    io()->text('Restoring frozen files...');
    checkoutFiles($remote, $branch, $filesToSync);

    // Re-read the frozen files list in case it was updated during sync (e.g. new files added to the list)
    /** @var string[] $updatedFrozenFiles */
    $updatedFrozenFiles = require TAPOMIX_FROZEN_FILES_SYNC_PATH;
    $updatedFilesToSync = computeFilesToSync($updatedFrozenFiles, $excludedFiles);
    $newFiles = \array_values(\array_diff($updatedFilesToSync, $filesToSync));

    if ([] !== $newFiles) {
        io()->text('Changes detected in updated frozen list, resyncing...');
        checkoutFiles($remote, $branch, $newFiles);
    }

    io()->success('Frozen files updated.');
}

/**
 * Checkout files from a remote branch.
 *
 * @param string[] $files
 */
function checkoutFiles(string $remote, string $branch, array $files): void
{
    run(['git', 'checkout', \sprintf('%s/%s', $remote, $branch), '--', ...$files]);
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
