import { ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';

import { RecordDetails } from '@/components/records/RecordDetails';
import type { CollectionRecord } from '@/components/records/types';
import type { ListRecord } from '@/modules/lists/types';

export function ListRecordCard({
    listId,
    record,
    number,
}: {
    listId: string;
    record: ListRecord;
    number: number;
}) {
    const [open, setOpen] = useState(false);
    const [details, setDetails] = useState<CollectionRecord | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);

    async function loadDetails() {
        setLoading(true);
        setError(false);
        try {
            const response = await fetch(
                `/lists/${listId}/records/${record.copyPublicId}`,
                { headers: { Accept: 'application/json' } },
            );
            if (!response.ok) throw new Error('Unable to load record');
            setDetails((await response.json()) as CollectionRecord);
        } catch {
            setError(true);
        } finally {
            setLoading(false);
        }
    }

    return (
        <li className="list-record-card" id={`record-${record.copyId}`}>
            <button
                className="list-record-toggle"
                type="button"
                aria-expanded={open}
                aria-controls={`details-${record.copyId}`}
                onClick={() => {
                    setOpen(!open);
                    if (!open && !details && !loading) void loadDetails();
                }}
            >
                <span className="list-record-number">
                    {String(number).padStart(2, '0')}
                </span>
                <img
                    src={record.cover}
                    alt=""
                    width={96}
                    height={96}
                    loading="lazy"
                />
                <span className="picker-record-text">
                    <strong>{record.displayTitle}</strong>
                    <span>{record.artist}</span>
                    <small>
                        {record.originalYear ?? record.year} · {record.format}
                    </small>
                </span>
                {open ? <ChevronUp size={22} /> : <ChevronDown size={22} />}
                <span className="sr-only">Tracks and listening links</span>
            </button>
            <div
                id={`details-${record.copyId}`}
                hidden={!open}
                className="list-record-details"
            >
                {loading && <p role="status">Loading tracks…</p>}
                {error && (
                    <p role="alert">
                        Couldn’t load this record.{' '}
                        <button
                            className="text-button"
                            type="button"
                            onClick={() => void loadDetails()}
                        >
                            Try again
                        </button>
                    </p>
                )}
                {details && (
                    <RecordDetails
                        record={record}
                        details={details}
                        titleId={`record-title-${record.copyId}`}
                    />
                )}
            </div>
        </li>
    );
}
