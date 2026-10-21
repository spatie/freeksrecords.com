import { useEffect, useRef, type CSSProperties } from 'react';

import { RecordLink } from '@/modules/social/RecordOverlay';
import type { RecordArtwork } from '@/modules/social/types';

export function HeroArtwork({
    records,
    moving = false,
}: {
    records: RecordArtwork[];
    moving?: boolean;
}) {
    const artwork = useRef<HTMLDivElement>(null);
    const artworkKey = JSON.stringify(records.map((record) => record.cover));

    useEffect(() => {
        if (!moving) return;
        const hero = artwork.current?.closest<HTMLElement>('.landing-hero');
        if (!hero) return;
        const positionBelt = () =>
            hero.style.setProperty(
                '--belt-angle',
                `${(-Math.atan2(hero.clientHeight * 0.38, hero.clientWidth) * 180) / Math.PI}deg`,
            );
        const observer = new ResizeObserver(positionBelt);
        observer.observe(hero);
        positionBelt();
        return () => observer.disconnect();
    }, [moving]);

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
            {Array.from({ length: moving ? 2 : 1 }, (_, run) => (
                <div className="artwork-run" key={run}>
                    {records.map((record, index) => (
                        <div
                            className="floating-sleeve"
                            key={record.url}
                            style={{ '--sleeve-order': index } as CSSProperties}
                        >
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
            ))}
        </div>
    );
}
