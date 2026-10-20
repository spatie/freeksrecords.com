import { Link } from '@inertiajs/react';

import { RecordLink } from '@/modules/social/RecordOverlay';
import type { Collector } from '@/modules/social/types';

export function CollectionPreview({ collector }: { collector: Collector }) {
    return (
        <article className="collection-preview">
            <div className="collection-preview-covers">
                {collector.records.slice(0, 4).map((record) => (
                    <RecordLink key={record.url} href={record.url}>
                        <img
                            src={record.cover}
                            alt={record.title}
                            loading="lazy"
                            width="180"
                            height="180"
                        />
                    </RecordLink>
                ))}
            </div>
            <Link
                href={collector.profileUrl}
                className="collection-preview-link"
                aria-label={`Browse ${collector.name}’s collection`}
            >
                <div className="collection-preview-caption">
                    <h2>{collector.name}</h2>
                    <p>@{collector.handle}</p>
                </div>
            </Link>
        </article>
    );
}
