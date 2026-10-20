import { useEffect, useRef } from 'react';

import { RecordLink } from '@/modules/social/RecordOverlay';
import type { RecordArtwork } from '@/modules/social/types';

export function HeroArtwork({ records }: { records: RecordArtwork[] }) {
    const artwork = useRef<HTMLDivElement>(null);
    const artworkKey = JSON.stringify(records.map((record) => record.cover));

    useEffect(() => {
        const element = artwork.current;
        if (!element) return;
        let cancelled = false;
        void Promise.allSettled(
            Array.from(element.querySelectorAll('img'), (image) =>
                image.decode(),
            ),
        ).then(() => {
            if (!cancelled) element.dataset.ready = 'true';
        });
        return () => {
            cancelled = true;
        };
    }, [artworkKey]);

    if (!records.length) return null;

    return (
        <div className="hero-artwork" key={artworkKey} ref={artwork}>
            {records.map((record) => (
                <div className="floating-sleeve" key={record.url}>
                    <RecordLink href={record.url}>
                        <img
                            src={record.cover}
                            alt={record.title}
                            decoding="async"
                            width="240"
                            height="240"
                        />
                    </RecordLink>
                </div>
            ))}
        </div>
    );
}
