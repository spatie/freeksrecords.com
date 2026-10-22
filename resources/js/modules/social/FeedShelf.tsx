import { Link } from '@inertiajs/react';

import '../../../css/feed-shelf.css';
import type { CollectionRecordSummary } from '@/components/records/types';
import { Avatar } from '@/modules/social/Avatar';
import { groupFeedRecords } from '@/modules/social/feedGroups';
import { RecordLink } from '@/modules/social/RecordOverlay';

export function FeedShelf({ records }: { records: CollectionRecordSummary[] }) {
    return (
        <div className="feed-shelves">
            {groupFeedRecords(records).map((group) => (
                <section className="feed-shelf" key={group.records[0].shareUrl}>
                    <header className="feed-shelf-heading">
                        {group.owner && (
                            <Link href={group.owner.profileUrl}>
                                <Avatar
                                    className="feed-avatar"
                                    person={group.owner}
                                />
                                <h2>{group.owner.name}</h2>
                            </Link>
                        )}
                        <time dateTime={group.date}>
                            {new Intl.DateTimeFormat('en-GB', {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric',
                                timeZone: 'Europe/Brussels',
                            }).format(new Date(group.records[0].addedAt))}
                        </time>
                    </header>
                    <ul className="feed-shelf-records">
                        {group.records.map((record) => (
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
                                </RecordLink>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}
