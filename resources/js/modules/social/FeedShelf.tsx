import { Link } from '@inertiajs/react';

import '../../../css/feed-shelf.css';
import { Avatar } from '@/modules/social/Avatar';
import { RecordLink } from '@/modules/social/RecordOverlay';
import type { FeedProps } from '@/modules/social/types';

const additionDate = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'Europe/Brussels',
});

export function FeedShelf({ friends }: Pick<FeedProps, 'friends'>) {
    return (
        <div className="feed-shelves">
            {friends.map(({ person, records, visible }) => (
                <section className="feed-shelf" key={person.publicId}>
                    <header className="feed-shelf-heading">
                        <Link href={person.profileUrl}>
                            <Avatar className="feed-avatar" person={person} />
                            <h2>{person.name}</h2>
                        </Link>
                        {visible && (
                            <Link
                                className="feed-collection-link"
                                href={person.profileUrl}
                                aria-label={`View ${person.name}’s full collection`}
                            >
                                View full collection{' '}
                                <span aria-hidden="true">→</span>
                            </Link>
                        )}
                    </header>
                    {!records.length && (
                        <p>
                            {visible
                                ? 'No records yet.'
                                : 'This collection is private.'}
                        </p>
                    )}
                    <ul className="feed-shelf-records">
                        {records.map((record) => (
                            <li key={record.shareUrl}>
                                <RecordLink href={record.shareUrl}>
                                    <img
                                        className="feed-cover"
                                        src={record.cover}
                                        alt=""
                                        loading="lazy"
                                        width="220"
                                        height="220"
                                    />
                                    <span className="feed-artist">
                                        {record.artist}
                                    </span>
                                    <span className="feed-album">
                                        {record.displayTitle}
                                    </span>
                                    <time
                                        className="feed-record-date"
                                        dateTime={record.addedAt}
                                    >
                                        {additionDate.format(
                                            new Date(record.addedAt),
                                        )}
                                    </time>
                                </RecordLink>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}
