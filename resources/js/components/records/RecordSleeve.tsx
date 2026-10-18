import { browseHash } from '@/components/records/browseState';

import type { CollectionRecordSummary } from '@/components/records/types';

type RecordSleeveProps = {
    browseBasePath?: string;
    onSelect: () => void;
    onArtist: (artist: string) => void;
    showYear?: boolean;
    record: CollectionRecordSummary;
    transitionRecordId: number | null;
};

export function RecordSleeve({
    browseBasePath = '/',
    onSelect,
    onArtist,
    showYear = false,
    record,
    transitionRecordId,
}: RecordSleeveProps) {
    return (
        <article
            className="catalog-record"
            data-record-id={record.instanceId}
            data-selected={transitionRecordId === record.instanceId}
        >
            <span className="catalog-record-content">
                <button
                    className="catalog-record-open"
                    type="button"
                    onClick={onSelect}
                    aria-label={`Take out ${record.displayTitle} by ${record.artist}`}
                >
                    <span className="catalog-record-art">
                        <img
                            alt=""
                            crossOrigin="anonymous"
                            decoding="async"
                            draggable={false}
                            height={600}
                            loading="lazy"
                            onLoad={(event) => {
                                event.currentTarget.dataset.loaded = 'true';
                            }}
                            src={record.cover}
                            width={600}
                        />
                        <span className="cover-gloss" />
                    </span>
                </button>
                <span className="catalog-record-label">
                    <strong>
                        {record.artists.map((artist, index) => (
                            <span key={artist}>
                                {index > 0 && ', '}
                                <a
                                    onClick={(event) => {
                                        if (
                                            event.metaKey ||
                                            event.ctrlKey ||
                                            event.shiftKey ||
                                            event.altKey
                                        )
                                            return;
                                        event.preventDefault();
                                        onArtist(artist);
                                    }}
                                    href={`${browseBasePath}${browseHash({ mode: 'artist', group: artist, query: '', visibleCount: 25 })}`}
                                >
                                    {artist}
                                </a>
                            </span>
                        ))}
                    </strong>
                    <span>{record.displayTitle}</span>
                    {showYear && record.originalYear && (
                        <time className="catalog-record-year">
                            {record.originalYear}
                        </time>
                    )}
                </span>
            </span>
        </article>
    );
}
