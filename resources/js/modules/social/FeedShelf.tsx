import { Link } from '@inertiajs/react';

import '../../../css/feed-shelf.css';
import { artworkThemeStyle } from '@/components/records/artworkLabel';
import type { CollectionRecordSummary } from '@/components/records/types';
import { RecordLink } from '@/modules/social/RecordOverlay';

export function FeedShelf({ records }: { records: CollectionRecordSummary[] }) {
    return (
        <div className="feed-tickets">
            {records.map((record) => (
                <article className="feed-ticket" key={record.shareUrl}>
                    <div className="ticket-who">
                        {record.owner && (
                            <Link href={record.owner.profileUrl}>
                                {record.owner.name}
                            </Link>
                        )}
                        <span> added</span>
                        <time dateTime={record.addedAt}>
                            {new Intl.DateTimeFormat('en-GB', {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric',
                                timeZone: 'Europe/Brussels',
                            }).format(new Date(record.addedAt))}
                        </time>
                    </div>
                    <div className="ticket-record">
                        <RecordLink href={record.shareUrl}>
                            <span className="ticket-title">
                                {record.artist}
                            </span>
                            <span className="ticket-album">
                                {record.displayTitle}
                            </span>
                            <span className="ticket-art">
                                <img
                                    src={record.cover}
                                    alt={`${record.displayTitle} by ${record.artist}`}
                                    loading="lazy"
                                    onLoad={(event) => {
                                        const row =
                                            event.currentTarget.closest<HTMLElement>(
                                                '.feed-ticket',
                                            );
                                        if (row)
                                            for (const [
                                                name,
                                                value,
                                            ] of Object.entries(
                                                artworkThemeStyle(
                                                    event.currentTarget,
                                                ),
                                            ))
                                                row.style.setProperty(
                                                    name,
                                                    value,
                                                );
                                    }}
                                    width="180"
                                    height="180"
                                />
                            </span>
                            <span className="ticket-arrow" aria-hidden="true">
                                ↗
                            </span>
                        </RecordLink>
                    </div>
                </article>
            ))}
        </div>
    );
}
