import { Head, Link, usePage } from '@inertiajs/react';

import { CollectionHeading } from '@/modules/social/CollectionHeading';
import { isSettingUpCollection } from '@/modules/social/collectionSetup';
import { DiscogsConnectButton } from '@/modules/social/DiscogsConnectButton';
import { FeedShelf } from '@/modules/social/FeedShelf';
import { ImportStatus } from '@/modules/social/ImportStatus';
import { Pagination } from '@/modules/social/Pagination';
import { ImportedShelf } from '@/modules/social/ProfileShelf';
import type { FeedProps, SocialProps } from '@/modules/social/types';

export function FeedPage({ importRecords, records }: FeedProps) {
    const { auth } = usePage<SocialProps>().props;
    const user = auth.user!;
    const starting = isSettingUpCollection(user);
    return (
        <>
            <Head
                title={
                    starting ? 'Add your records' : 'New records from friends'
                }
            />
            {starting ? (
                <section
                    className={
                        user.discogsConnected
                            ? 'importing-collection'
                            : 'onboarding'
                    }
                    aria-labelledby="onboarding-title"
                >
                    {!user.discogsConnected && (
                        <ol className="setup-steps" aria-label="Account setup">
                            <li>Account created</li>
                            <li>Email verified</li>
                            <li aria-current="step">Import collection</li>
                        </ol>
                    )}
                    <CollectionHeading
                        id="onboarding-title"
                        title={
                            user.discogsConnected
                                ? 'Your collection'
                                : 'Add your records'
                        }
                        user={user}
                    />
                    {user.discogsConnected ? (
                        <>
                            <ImportStatus user={user} />
                            {importRecords.length > 0 && (
                                <ImportedShelf records={importRecords} />
                            )}
                        </>
                    ) : (
                        <>
                            <p className="lede">
                                Bring your collection over from Discogs. Your
                                records will appear on your own shelf, ready to
                                browse and share.
                            </p>
                            <DiscogsConnectButton />
                            <p className="onboarding-detail">
                                You’ll approve the connection on Discogs. We
                                never see your password.{' '}
                                {user.visibility === 'only_me'
                                    ? 'Only you can see your collection.'
                                    : 'Your current sharing preference applies to imported records.'}
                            </p>
                            <details className="discogs-help">
                                <summary>
                                    Don’t have a collection on Discogs yet?
                                </summary>
                                <p>
                                    Add your vinyl, CDs or cassettes to{' '}
                                    <a
                                        href="https://www.discogs.com/my"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        your Discogs collection
                                    </a>
                                    , then come back and connect. Records are
                                    added on Discogs, not directly on Plaat
                                    Club.
                                </p>
                                <p>
                                    Your first import stays off your friends’
                                    feeds.
                                </p>
                            </details>
                        </>
                    )}
                </section>
            ) : (
                <div className="page-heading">
                    <h1>Friends’ finds.</h1>
                    <p className="lede">
                        The latest additions to your friends’ collections,
                        newest first.
                    </p>
                </div>
            )}
            {!starting && (
                <div className="feed-layout">
                    <section
                        className="feed-main"
                        aria-label="Friends’ additions"
                    >
                        {records.data.length ? (
                            <FeedShelf records={records.data} />
                        ) : (
                            <section className="empty panel">
                                <h2>Nothing new yet.</h2>
                                <p>
                                    New records shared by your friends appear
                                    here.
                                </p>
                                <div className="actions">
                                    <Link className="button" href="/friends">
                                        Find friends
                                    </Link>
                                    <Link href="/collections">
                                        Browse collections
                                    </Link>
                                </div>
                            </section>
                        )}
                        <Pagination {...records} />
                    </section>
                </div>
            )}
        </>
    );
}
