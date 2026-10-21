import { router, usePoll } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';

import type { SocialProps, Viewer } from '@/modules/social/types';

export function ImportStatus({ user }: { user: Viewer }) {
    const [pending, setPending] = useState(false);
    const [requested, setRequested] = useState(false);
    usePoll(5000, {
        only: ['auth', 'collectionCount', 'importRecords'],
        onSuccess: (page) => {
            const current = (page.props.auth as SocialProps['auth']).user;
            if (!current?.syncStatus?.startsWith('Sync failed'))
                setRequested(false);
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
                    {requested
                        ? 'Waiting to resume'
                        : failed
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
            {requested ? (
                <span className="import-confirmation">
                    <Check size={15} aria-hidden="true" />
                    Retry requested
                </span>
            ) : (
                failed && (
                    <button
                        className="text-button"
                        disabled={pending}
                        onClick={() => {
                            setPending(true);
                            router.post(
                                '/discogs/sync',
                                {},
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setRequested(true),
                                    onFinish: () => setPending(false),
                                },
                            );
                        }}
                    >
                        {pending ? 'Requesting…' : 'Retry import'}
                    </button>
                )
            )}
        </div>
    );
}
