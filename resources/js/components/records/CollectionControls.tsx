import { Search, X } from 'lucide-react';
import type { Ref } from 'react';

import type { CollectionMode } from '@/components/records/types';

type CollectionControlsProps = {
    mode: CollectionMode;
    query: string;
    onMode: (mode: CollectionMode) => void;
    onQuery: (query: string) => void;
    ref?: Ref<HTMLDivElement>;
};

const modes: { key: CollectionMode; label: string }[] = [
    { key: 'recent', label: 'Latest additions' },
    { key: 'artist', label: 'Artist' },
    { key: 'decade', label: 'Decade' },
    { key: 'genre', label: 'Genre' },
];

export function CollectionControls({
    mode,
    query,
    onMode,
    onQuery,
    ref,
}: CollectionControlsProps) {
    return (
        <div className="collection-controls" ref={ref}>
            <nav aria-label="Group collection" className="collection-tabs">
                {modes.map((option) => (
                    <button
                        aria-pressed={mode === option.key}
                        key={option.key}
                        onClick={() => onMode(option.key)}
                        type="button"
                    >
                        {option.label}
                    </button>
                ))}
            </nav>
            <div className="record-search">
                <Search aria-hidden="true" size={20} />
                <input
                    aria-label="Search records"
                    onChange={(event) => onQuery(event.target.value)}
                    placeholder="Find a record…"
                    type="search"
                    value={query}
                />
                {query && (
                    <button
                        aria-label="Clear search"
                        onClick={() => onQuery('')}
                        type="button"
                    >
                        <X size={18} />
                    </button>
                )}
            </div>
        </div>
    );
}
