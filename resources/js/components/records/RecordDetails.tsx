import { formatReleaseDate } from '@/components/records/collection';
import { collectionAddedDate } from '@/components/records/collectionDates';
import type { CSSProperties } from 'react';
import { ArrowUpRight } from 'lucide-react';

import { useRecordDetails } from '@/components/records/useRecordDetails';
import { AppleIcon } from '@/components/records/AppleIcon';
import { SpotifyIcon } from '@/components/records/SpotifyIcon';
import type {
    CollectionRecord,
    CollectionRecordSummary,
} from '@/components/records/types';

export function RecordDetails({
    record: summary,
    onArtist,
    titleId,
    details: supplied,
}: {
    record: CollectionRecordSummary;
    details?: CollectionRecord;
    onArtist: (artist: string) => void;
    titleId: string;
}) {
    const { details, error, retry } = useRecordDetails(
        summary.publicId,
        supplied,
    );
    const record = details ?? summary;
    const tracks = details?.tracks ?? [];
    const addedDate = collectionAddedDate(record);
    return (
        <>
            <div className="listening-artists">
                {record.artists.map((artist) => (
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
                ))}
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

            <div className="album-listen-links">
                <a
                    aria-label={
                        record.appleMusicSearch
                            ? 'Find this release on Apple Music'
                            : 'Open this release on Apple Music'
                    }
                    href={record.appleMusicUrl}
                    rel="noopener noreferrer"
                    target="_blank"
                    title="Apple Music"
                >
                    <AppleIcon size={24} />
                </a>
                <a
                    aria-label={
                        record.spotifySearch
                            ? 'Find this release on Spotify'
                            : 'Open this release on Spotify'
                    }
                    href={record.spotifyUrl}
                    rel="noopener noreferrer"
                    target="_blank"
                    title="Spotify"
                >
                    <SpotifyIcon size={24} />
                </a>
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
            <p className="record-attribution">
                <a
                    href={`https://www.discogs.com/release/${record.id}`}
                    rel="noopener noreferrer"
                    target="_blank"
                >
                    Data provided by Discogs ↗
                </a>
            </p>
        </>
    );
}
