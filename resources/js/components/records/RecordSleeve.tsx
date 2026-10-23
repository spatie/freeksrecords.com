import { Link } from '@inertiajs/react';

import { browseHash } from '@/components/records/browseState';
import '../../../css/record-cases.css';

import type { CollectionRecordSummary } from '@/components/records/types';

type RecordSleeveProps = {
    browseBasePath?: string;
    onSelect?: (source: HTMLElement) => void;
    onArtist?: (artist: string) => void;
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
    const SleeveAction = onSelect ? 'button' : 'a';
    const OwnerLink = record.owner ? Link : 'a';

    return (
        <article
            className="catalog-record"
            data-media-format={record.mediaFormats?.[0]}
            data-record-id={record.instanceId}
            data-selected={transitionRecordId === record.instanceId}
        >
            <span className="catalog-record-content">
                <SleeveAction
                    className="catalog-record-open"
                    type={onSelect ? 'button' : undefined}
                    href={onSelect ? undefined : record.shareUrl}
                    onClick={(event) => onSelect?.(event.currentTarget)}
                    aria-label={`${onSelect ? 'View' : 'Take out'} ${record.displayTitle} by ${record.artist}${record.mediaFormats?.length ? ` (${record.mediaFormats.join(', ')})` : ''}`}
                >
                    <span className="catalog-record-art">
                        <span
                            className="catalog-record-image"
                            style={
                                record.coverPreview
                                    ? {
                                          backgroundImage: `url("${record.coverPreview}")`,
                                      }
                                    : undefined
                            }
                        >
                            <img
                                key={record.cover}
                                alt=""
                                decoding="async"
                                draggable={false}
                                height={600}
                                loading="lazy"
                                ref={(image) => {
                                    if (
                                        image?.complete &&
                                        image.naturalWidth > 0
                                    ) {
                                        image.dataset.loaded = 'true';
                                    }
                                }}
                                onLoad={(event) => {
                                    event.currentTarget.dataset.loaded = 'true';
                                }}
                                src={record.cover}
                                width={600}
                            />
                        </span>
                        {record.mediaFormats?.[0] === 'Cassette' && (
                            <span className="cassette-inlay" aria-hidden="true">
                                <strong>{record.artist}</strong>
                                <span>{record.displayTitle}</span>
                                <small>Cassette</small>
                            </span>
                        )}
                        <span className="cover-gloss" />
                    </span>
                </SleeveAction>
                <span className="catalog-record-label">
                    <strong>
                        {record.artists.map((artist, index) => (
                            <span key={artist}>
                                {index > 0 && ', '}
                                <OwnerLink
                                    onClick={(event) => {
                                        if (
                                            !onArtist ||
                                            event.metaKey ||
                                            event.ctrlKey ||
                                            event.shiftKey ||
                                            event.altKey
                                        )
                                            return;
                                        event.preventDefault();
                                        onArtist(artist);
                                    }}
                                    href={`${record.owner?.profileUrl ?? browseBasePath}${browseHash({ mode: 'artist', group: artist, query: '', visibleCount: 25 })}`}
                                >
                                    {artist}
                                </OwnerLink>
                            </span>
                        ))}
                    </strong>
                    <span>{record.displayTitle}</span>
                    {showYear && record.originalYear && (
                        <time className="catalog-record-year">
                            {record.originalYear}
                        </time>
                    )}
                    {record.owner && (
                        <small className="catalog-record-activity">
                            <Link href={record.owner.profileUrl}>
                                {record.owner.name}
                            </Link>
                            {' · '}
                            <time dateTime={record.addedAt}>
                                {new Date(record.addedAt).toLocaleDateString(
                                    'en-GB',
                                    {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                        timeZone: 'Europe/Brussels',
                                    },
                                )}
                            </time>
                        </small>
                    )}
                </span>
            </span>
        </article>
    );
}
