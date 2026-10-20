import { Link, router, usePoll } from '@inertiajs/react';

import type { Viewer } from '@/modules/social/types';

export function ImportStatus({ user }: { user: Viewer }) {
    usePoll(5000, { only: ['auth', 'collectionCount'] });
    return (
        <div className="import-status">
            <div className="import-progress" role="status">
                <strong>
                    {user.importedAt
                        ? 'Your collection is ready'
                        : (user.syncStatus ??
                          'Waiting for the import to start')}
                </strong>
                <p>
                    {user.collectionCount}{' '}
                    {user.collectionCount === 1 ? 'record' : 'records'} imported
                </p>
            </div>
            {!user.importedAt && (
                <p className="muted">
                    Large collections can take a while. You can leave this page;
                    the import continues in the background.
                </p>
            )}
            <p className="muted">
                Collection visibility:{' '}
                {user.visibility === 'everyone'
                    ? 'Everyone'
                    : user.visibility === 'friends'
                      ? 'Friends'
                      : 'Only me'}
                . <Link href="/settings">Change visibility</Link>
            </p>
            <div className="actions">
                {user.importedAt && (
                    <Link className="button" href={user.profileUrl}>
                        View my collection
                    </Link>
                )}
                {user.syncStatus?.startsWith('Sync failed') && (
                    <button
                        className="button secondary"
                        onClick={() => router.post('/discogs/sync')}
                    >
                        Retry import
                    </button>
                )}
                <button
                    className="text-button"
                    onClick={() =>
                        router.reload({ only: ['auth', 'collectionCount'] })
                    }
                >
                    Check progress
                </button>
                <Link href="/settings">Connection settings</Link>
            </div>
        </div>
    );
}
