import { router, usePoll } from '@inertiajs/react';

import type { SocialProps, Viewer } from '@/modules/social/types';

export function ImportStatus({ user }: { user: Viewer }) {
    usePoll(5000, {
        only: ['auth', 'collectionCount', 'importRecords'],
        onSuccess: (page) => {
            const current = (page.props.auth as SocialProps['auth']).user;
            if (current?.importedAt) router.visit(current.profileUrl);
        },
    });
    const failed = user.syncStatus?.startsWith('Sync failed');
    const retrying = user.syncStatus?.startsWith('Retrying automatically');
    return (
        <div
            className={`import-status ${failed ? 'import-failed' : ''}`}
            role="status"
        >
            <span className="import-label">
                <span className="import-record" aria-hidden="true" />
                <strong>
                    {failed
                        ? 'Import paused'
                        : retrying
                          ? 'Retrying automatically'
                          : 'Still importing'}
                </strong>
            </span>
            <span className="import-count">
                {user.collectionCount}
                {user.syncTotal !== null && ` of ${user.syncTotal}`}{' '}
                {(user.syncTotal ?? user.collectionCount) === 1
                    ? 'record'
                    : 'records'}{' '}
                imported
            </span>
            {failed && (
                <button
                    className="text-button"
                    onClick={() => router.post('/discogs/sync')}
                >
                    Retry import
                </button>
            )}
        </div>
    );
}
