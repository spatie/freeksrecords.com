import { formatReleaseDate } from '@/components/records/collection';
import { collectionAddedDate } from '@/components/records/collectionDates';
import type { CSSProperties, ReactNode } from 'react';
import { ArrowUpRight } from 'lucide-react';

import { useRecordDetails } from '@/components/records/useRecordDetails';
import { AppleIcon } from '@/components/records/AppleIcon';
import { SpotifyIcon } from '@/components/records/SpotifyIcon';
import { RecordLinerNotes } from '@/components/records/RecordLinerNotes';
import { safeExternalUrl } from '@/components/records/discogsText';
import type {
    CollectionRecord,
    CollectionRecordSummary,
} from '@/components/records/types';

export function RecordDetails({
    actions,
    record: summary,
    onArtist,
    titleId,
    details: supplied,
}: {
    actions?: ReactNode;
    record: CollectionRecordSummary;
    details?: CollectionRecord;
    onArtist?: (artist: string) => void;
    titleId: string;
}) {
    const { details, error, retry } = useRecordDetails(
        summary.publicId,
        supplied,
    );
    const record = details ?? summary;
    const tracks = details?.tracks ?? [];
    const addedDate = collectionAddedDate(record);
    const bandcampUrl =
        record.bandcampSearch === false
            ? safeExternalUrl(record.bandcampUrl)
            : undefined;
    return (
        <>
            <div className="listening-artists">
                {record.artists.map((artist) =>
                    onArtist ? (
                        <button
                            className="listening-artist"
                            key={artist}
                            onClick={() => onArtist(artist)}
                            type="button"
                            title={`All records by ${artist}`}
                        >
                            {artist}
                            <ArrowUpRight size={18} />
                        </button>
                    ) : (
                        <span key={artist}>{artist}</span>
                    ),
                )}
            </div>
            <h1 className="listening-title" id={titleId} tabIndex={-1}>
                {record.displayTitle}
            </h1>
            <div className="record-facts">
                <span>
                    {record.originalYear
                        ? `Released ${formatReleaseDate(record.originalReleaseDate ?? String(record.originalYear))}`
                        : 'Original year unknown'}
                </span>
                <span>{record.genres.join(' / ')}</span>
            </div>
            {addedDate && (
                <p className="record-collection-date">
                    Added to collection ·{' '}
                    <time dateTime={addedDate}>
                        {formatReleaseDate(addedDate)}
                    </time>
                </p>
            )}

            {actions}
            <div className="album-listen-links">
                <a
                    aria-label={
                        record.appleMusicSearch
                            ? 'Find this release on Apple Music'
                            : 'Open this release on Apple Music'
                    }
                    href={safeExternalUrl(record.appleMusicUrl)}
                    rel="noopener noreferrer"
                    target="_blank"
                    title="Apple Music"
                >
                    <AppleIcon size={24} />
                    <span className="album-service-name">Apple Music</span>
                </a>
                <a
                    aria-label={
                        record.spotifySearch
                            ? 'Find this release on Spotify'
                            : 'Open this release on Spotify'
                    }
                    href={safeExternalUrl(record.spotifyUrl)}
                    rel="noopener noreferrer"
                    target="_blank"
                    title="Spotify"
                >
                    <SpotifyIcon size={24} />
                    <span className="album-service-name">Spotify</span>
                </a>
                {bandcampUrl && (
                    <a
                        aria-label="Open this release on Bandcamp"
                        href={bandcampUrl}
                        rel="noopener noreferrer"
                        target="_blank"
                        title="Bandcamp"
                    >
                        <svg
                            aria-hidden="true"
                            width={24}
                            height={24}
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path d="M6 5h18l-6 14H0z" />
                        </svg>
                        <span className="album-service-name">Bandcamp</span>
                    </a>
                )}
            </div>
            <div className="listening-tracklist">
                <div className="tracks-heading">
                    <h2>Tracks</h2>
                </div>
                <div role="status">
                    {error && (
                        <p className="tracks-unavailable">
                            Couldn’t load the tracks.{' '}
                            <button onClick={retry} type="button">
                                Try again
                            </button>
                        </p>
                    )}
                </div>
                <ol
                    style={
                        {
                            '--track-count': summary.trackTitles.length,
                        } as CSSProperties
                    }
                    aria-busy={!details && !error}
                >
                    {!details &&
                        !error &&
                        summary.trackTitles.map((title, index) => (
                            <li
                                className="track-placeholder"
                                key={`${index}-${title}`}
                            >
                                <span className="track-position">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                <span className="track-name">{title}</span>
                            </li>
                        ))}
                    {tracks.map((track, index) => (
                        <li key={`${index}-${track.position}-${track.title}`}>
                            <span className="track-position">
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <span className="track-name">
                                {track.title}
                                {track.artist !== record.artist && (
                                    <small>{track.artist}</small>
                                )}
                            </span>
                            <span className="track-duration">
                                {track.duration}
                            </span>
                        </li>
                    ))}
                </ol>
                {details && tracks.length === 0 && (
                    <p className="tracks-unavailable">
                        No tracklist available for this release.
                    </p>
                )}
            </div>
            {details && <RecordLinerNotes key={record.id} record={details} />}
        </>
    );
}
