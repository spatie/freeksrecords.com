import { SearchX } from 'lucide-react';
import type { CSSProperties } from 'react';
import { useEffect, useEffectEvent, useRef } from 'react';

import {
    formatReleaseDate,
    recordsByAddedDate,
    recordsByYear,
} from '@/components/records/collection';
import { RecordSleeve } from '@/components/records/RecordSleeve';
import type { CollectionRecordSummary } from '@/components/records/types';

type RecordShelfProps = {
    browseBasePath?: string;
    groupBy: 'year' | 'added' | null;
    showYears?: boolean;
    onMore: () => void;
    onArtist?: (artist: string) => void;
    onReset: () => void;
    onSelect?: (record: CollectionRecordSummary, source: HTMLElement) => void;
    records: CollectionRecordSummary[];
    transitionRecordId: number | null;
    visibleCount: number;
};

export function RecordShelf({
    browseBasePath,
    groupBy,
    showYears = false,
    onMore,
    onArtist,
    onReset,
    onSelect,
    records,
    transitionRecordId,
    visibleCount,
}: RecordShelfProps) {
    const sentinel = useRef<HTMLDivElement>(null);
    const loadMore = useEffectEvent(onMore);
    const hasMore = visibleCount < records.length;

    useEffect(() => {
        if (!hasMore || !sentinel.current) return;
        const observer = new IntersectionObserver(
            (entries) => {
                if (!entries.some((entry) => entry.isIntersecting)) return;
                observer.disconnect();
                loadMore();
            },
            { rootMargin: '800px 0px' },
        );
        observer.observe(sentinel.current);
        return () => observer.disconnect();
    }, [hasMore, visibleCount, records]);

    if (records.length === 0) {
        return (
            <div className="empty-shelf">
                <SearchX size={36} strokeWidth={1.5} />
                <h2>Nothing on this shelf.</h2>
                <p>Try another artist, album, year or track.</p>
                <button onClick={onReset} type="button">
                    Clear search
                </button>
            </div>
        );
    }
    const shown = records.slice(0, visibleCount);
    const sections =
        groupBy === 'added'
            ? recordsByAddedDate(shown).map(({ date, records }) => ({
                  heading: formatReleaseDate(date),
                  records,
              }))
            : groupBy === 'year'
              ? recordsByYear(shown).map(({ year, records }) => ({
                    heading: year,
                    records,
                }))
              : [{ heading: '', records: shown }];
    return (
        <div
            className={`shelf-results ${records.length < 4 ? 'has-small-collection' : ''} ${records.length <= 4 ? 'has-single-shelf' : ''}`}
            style={
                {
                    '--record-count': records.length,
                } as CSSProperties
            }
        >
            <div className="record-cabinet">
                {sections.map((section) => (
                    <section
                        className="cabinet-year"
                        key={section.heading || 'all'}
                        aria-label={section.heading || 'Records'}
                    >
                        {groupBy && (
                            <h3 className="cabinet-year-heading">
                                <span>{section.heading}</span>
                            </h3>
                        )}
                        <div className="catalog-grid">
                            {section.records.map((record) => (
                                <RecordSleeve
                                    browseBasePath={browseBasePath}
                                    key={
                                        record.owner
                                            ? record.shareUrl
                                            : record.instanceId
                                    }
                                    onSelect={
                                        onSelect
                                            ? (source) =>
                                                  onSelect(record, source)
                                            : undefined
                                    }
                                    onArtist={onArtist}
                                    record={record}
                                    showYear={showYears}
                                    transitionRecordId={transitionRecordId}
                                />
                            ))}
                        </div>
                    </section>
                ))}
            </div>
            {hasMore && (
                <div
                    aria-hidden="true"
                    className="shelf-load-sentinel"
                    ref={sentinel}
                />
            )}
        </div>
    );
}
