import { Link } from '@inertiajs/react';

import type { Collector } from '@/modules/social/types';

export function CollectionPreview({ collector }: { collector: Collector }) {
    return (
        <article className="collection-preview">
            <Link
                href={collector.profileUrl}
                className="collection-preview-link"
                aria-label={`Browse ${collector.name}’s collection`}
            >
                <div className="collection-preview-covers" aria-hidden="true">
                    {collector.covers.slice(0, 4).map((cover, index) => (
                        <img
                            key={index}
                            src={cover}
                            alt=""
                            loading="lazy"
                            width="180"
                            height="180"
                        />
                    ))}
                </div>
                <div className="collection-preview-caption">
                    <h2>{collector.name}</h2>
                    <p>
                        @{collector.handle}{' '}
                        <span>
                            {collector.recordCount.toLocaleString()}{' '}
                            {collector.recordCount === 1 ? 'record' : 'records'}
                        </span>
                    </p>
                </div>
            </Link>
        </article>
    );
}
