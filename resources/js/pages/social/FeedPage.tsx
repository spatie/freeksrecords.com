import { Head, Link, usePage } from '@inertiajs/react';

import { FeedShelf } from '@/modules/social/FeedShelf';
import { FriendActions } from '@/modules/social/FriendActions';
import { ImportStatus } from '@/modules/social/ImportStatus';
import { Pagination } from '@/modules/social/Pagination';
import type { FeedProps, SocialProps } from '@/modules/social/types';

export function FeedPage({
    collectionCount,
    incomingRequests,
    records,
}: FeedProps) {
    const { auth, csrfToken } = usePage<SocialProps>().props;
    const user = auth.user!;
    const starting =
        !user.importedAt && (!collectionCount || user.discogsConnected);
    return (
        <>
            <Head
                title={starting ? 'Add your records' : 'Friends’ additions'}
            />
            {starting ? (
                <section
                    className="onboarding"
                    aria-labelledby="onboarding-title"
                >
                    <ol className="setup-steps" aria-label="Account setup">
                        <li>Account created</li>
                        <li>Email verified</li>
                        <li aria-current="step">Import collection</li>
                    </ol>
                    <h1 id="onboarding-title">Add your records</h1>
                    {user.discogsConnected ? (
                        <ImportStatus user={user} />
                    ) : (
                        <>
                            <p className="lede">
                                Bring your collection over from Discogs. Your
                                records will appear on your own shelf, ready to
                                browse and share.
                            </p>
                            <form method="post" action="/discogs/connect">
                                <input
                                    type="hidden"
                                    name="_token"
                                    value={csrfToken}
                                />
                                <button className="button">
                                    Connect Discogs
                                </button>
                            </form>
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
                    <p className="page-eyebrow">Friends’ additions</p>
                    <h1>Fresh on their shelves.</h1>
                </div>
            )}
            {(!starting ||
                records.data.length > 0 ||
                incomingRequests.length > 0) && (
                <div
                    className={`feed-layout ${records.data.length ? 'feed-cupboard-layout' : ''}`}
                >
                    <section
                        className="feed-main"
                        aria-label="Friends’ additions"
                    >
                        {starting && records.data.length > 0 && (
                            <h2>Friends’ additions</h2>
                        )}
                        {records.data.length ? (
                            <FeedShelf records={records.data} />
                        ) : (
                            !starting && (
                                <section className="empty panel">
                                    <h2>Nothing new yet.</h2>
                                    <p>
                                        New records shared by your friends
                                        appear here.
                                    </p>
                                    <div className="actions">
                                        <Link
                                            className="button"
                                            href="/friends"
                                        >
                                            Find friends
                                        </Link>
                                        <Link href="/collections">
                                            Browse collections
                                        </Link>
                                    </div>
                                </section>
                            )
                        )}
                        <Pagination {...records} />
                    </section>
                    <aside
                        className="feed-sidebar"
                        aria-label="Your collection and requests"
                    >
                        {!starting && (
                            <section className="panel">
                                <h2>My collection</h2>
                                <Link href={user.profileUrl}>
                                    View my collection
                                </Link>
                            </section>
                        )}
                        {incomingRequests.length > 0 && (
                            <section className="panel">
                                <h2>Friend requests</h2>
                                {incomingRequests.map((person) => (
                                    <div
                                        className="incoming-request"
                                        key={person.id}
                                    >
                                        <Link href={person.profileUrl}>
                                            {person.name}
                                        </Link>
                                        <p className="muted">
                                            @{person.handle}
                                        </p>
                                        <FriendActions
                                            personId={person.id}
                                            actions={[
                                                {
                                                    value: 'accept',
                                                    label: 'Accept',
                                                },
                                                {
                                                    value: 'decline',
                                                    label: 'Decline',
                                                },
                                            ]}
                                        />
                                    </div>
                                ))}
                            </section>
                        )}
                    </aside>
                </div>
            )}
        </>
    );
}
