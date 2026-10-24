import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { SortableListRecord } from '@/modules/lists/SortableListRecord';
import type { ListEditProps, PickerRecord } from '@/modules/lists/types';
import { VisibilityPicker } from '@/modules/social/VisibilityPicker';
import '../../../css/record-lists.css';

export function ListEditPage({ list, records, results, query }: ListEditProps) {
    const form = useForm(`RecordList:${list.publicId}`, {
        name: list.name,
        description: list.description ?? '',
        visibility: list.visibility,
        selection: records as PickerRecord[],
    });
    const [search, setSearch] = useState(query);
    const [review, setReview] = useState(false);
    const [searching, setSearching] = useState(false);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const cancelSearch = useRef<(() => void) | null>(null);
    const base = `/lists/${list.publicId}`;
    const selected = form.data.selection;
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    useEffect(
        () => () => {
            if (searchTimer.current) clearTimeout(searchTimer.current);
            cancelSearch.current?.();
        },
        [],
    );

    useEffect(() => {
        if (!form.isDirty) return;
        const preventUnload = (event: BeforeUnloadEvent) =>
            event.preventDefault();
        window.addEventListener('beforeunload', preventUnload);
        const stop = router.on('before', (event) => {
            if (
                event.detail.visit.method !== 'get' ||
                event.detail.visit.url.pathname === `${base}/edit`
            )
                return;
            if (!window.confirm('Leave without saving your list changes?'))
                event.preventDefault();
        });
        return () => {
            window.removeEventListener('beforeunload', preventUnload);
            stop();
        };
    }, [form.isDirty, base]);

    function visit(url: string, paginate = false) {
        if (form.processing) return;
        cancelSearch.current?.();
        setSearching(true);
        router.get(
            url,
            {},
            {
                only: ['results', 'query'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onCancelToken: (token) => {
                    cancelSearch.current = token.cancel;
                },
                onSuccess: () => {
                    if (paginate)
                        document
                            .getElementById('record-search')
                            ?.scrollIntoView({ block: 'center' });
                },
                onFinish: () => setSearching(false),
            },
        );
    }

    function searchFor(value: string) {
        if (form.processing) return;
        setSearch(value);
        cancelSearch.current?.();
        setSearching(true);
        if (searchTimer.current) clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(
            () => visit(`${base}/edit?q=${encodeURIComponent(value)}`),
            250,
        );
    }

    return (
        <>
            <Head title={`Pick records · ${list.name}`} />
            <div className="list-editor-heading">
                <Link href={base}>← Back to list</Link>
                <p className="page-eyebrow">Edit your list</p>
                <h1>{list.name}</h1>
                <p>
                    Find records in your collection, then drag your picks into
                    order. Save your list when it feels right.
                </p>
            </div>
            {list.removedCount > 0 && (
                <p className="notice">
                    {list.removedCount}{' '}
                    {list.removedCount === 1 ? 'record was' : 'records were'}{' '}
                    removed from your Discogs collection and no longer appear in
                    this list. Save to acknowledge.
                </p>
            )}
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    if (searchTimer.current) clearTimeout(searchTimer.current);
                    cancelSearch.current?.();
                    form.transform(({ selection, ...data }) => ({
                        ...data,
                        recordIds: selection.map((record) => record.copyId),
                    }));
                    form.patch(base);
                }}
            >
                <section
                    className="list-edit-metadata"
                    aria-label="List settings"
                >
                    <div className="list-metadata">
                        <label htmlFor="name">
                            List name
                            <input
                                id="name"
                                required
                                maxLength={120}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </label>
                        <label htmlFor="description">
                            <span>
                                A few words <small>Optional</small>
                            </span>
                            <textarea
                                id="description"
                                rows={2}
                                maxLength={2000}
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                    </div>
                    <div className="list-editor-visibility">
                        <span>Visibility</span>
                        <VisibilityPicker
                            disabled={form.processing}
                            error={form.errors.visibility}
                            label="List visibility"
                            value={form.data.visibility}
                            onChange={(value) =>
                                form.setData('visibility', value)
                            }
                        />
                    </div>
                </section>
                <div
                    className="picker-mobile-tabs"
                    aria-label="Record picker view"
                >
                    <button
                        type="button"
                        aria-pressed={!review}
                        onClick={() => setReview(false)}
                    >
                        1. Add records
                    </button>
                    <button
                        type="button"
                        aria-pressed={review}
                        onClick={() => setReview(true)}
                    >
                        2. Arrange ({selected.length})
                    </button>
                </div>
                <div className={`record-picker ${review ? 'reviewing' : ''}`}>
                    <section
                        className="picker-results"
                        aria-label="Find records"
                    >
                        <h2 className="picker-step-title">
                            <span>1</span> Add records
                        </h2>
                        <p className="muted">
                            Search your collection. Your picks stay while you
                            browse.
                        </p>
                        <label
                            className="picker-search"
                            htmlFor="record-search"
                        >
                            <Search size={21} aria-hidden="true" />
                            <span className="sr-only">
                                Search your collection
                            </span>
                            <input
                                id="record-search"
                                type="search"
                                placeholder="Search artist, album, year…"
                                maxLength={200}
                                disabled={form.processing}
                                value={search}
                                onChange={(event) =>
                                    searchFor(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter')
                                        event.preventDefault();
                                }}
                            />
                        </label>
                        <p className="muted" role="status">
                            {searching
                                ? 'Searching…'
                                : search
                                  ? `Results for “${query}”`
                                  : 'Recently added to your collection'}
                        </p>
                        <div aria-busy={searching} className="picker-rows">
                            {results.data.map((record) => {
                                const added = selected.some(
                                    (entry) => entry.copyId === record.copyId,
                                );
                                return (
                                    <div
                                        className="picker-row"
                                        key={record.copyId}
                                    >
                                        <img
                                            src={record.cover}
                                            alt=""
                                            width={56}
                                            height={56}
                                            loading="lazy"
                                        />
                                        <div className="picker-record-text">
                                            <strong>
                                                {record.displayTitle}
                                            </strong>
                                            <span>{record.artist}</span>
                                            <small>
                                                {record.originalYear ??
                                                    record.year}{' '}
                                                · {record.format}
                                            </small>
                                        </div>
                                        <button
                                            className={`picker-add ${added ? 'is-added' : ''}`}
                                            type="button"
                                            disabled={
                                                added ||
                                                searching ||
                                                selected.length >= 1000
                                            }
                                            aria-label={`${added ? 'Added' : 'Add'} ${record.displayTitle} by ${record.artist}`}
                                            onClick={() =>
                                                form.setData('selection', [
                                                    ...selected,
                                                    record,
                                                ])
                                            }
                                        >
                                            {added ? (
                                                <Check size={17} />
                                            ) : (
                                                <Plus size={17} />
                                            )}
                                            {added ? 'Added' : 'Add'}
                                        </button>
                                    </div>
                                );
                            })}
                            {results.data.length === 0 && (
                                <div className="list-empty">
                                    <h3>
                                        {search
                                            ? 'Nothing found this time.'
                                            : 'Your collection is empty.'}
                                    </h3>
                                    <p>
                                        {search
                                            ? 'Try an artist, a shorter title or a different spelling.'
                                            : 'Import your records from Discogs in Settings, then come back to make your selection.'}
                                    </p>
                                    {!search && (
                                        <Link href="/settings">
                                            Go to Settings
                                        </Link>
                                    )}
                                </div>
                            )}
                        </div>
                        <div className="actions picker-pagination">
                            {results.prev_page_url && (
                                <button
                                    className="button secondary"
                                    type="button"
                                    disabled={searching || form.processing}
                                    onClick={() =>
                                        visit(results.prev_page_url!, true)
                                    }
                                >
                                    Previous records
                                </button>
                            )}
                            {results.next_page_url && (
                                <button
                                    className="button secondary"
                                    type="button"
                                    disabled={searching || form.processing}
                                    onClick={() =>
                                        visit(results.next_page_url!, true)
                                    }
                                >
                                    More records
                                </button>
                            )}
                        </div>
                    </section>
                    <aside
                        className="picker-selection"
                        aria-labelledby="selected-title"
                    >
                        <h2 id="selected-title">
                            <span className="picker-step-number">2</span>{' '}
                            Arrange your list
                        </h2>
                        <p className="muted">
                            Drag the handles to reorder. This is how others will
                            see your list.
                        </p>
                        <p className="selection-count" role="status">
                            {selected.length}{' '}
                            {selected.length === 1 ? 'record' : 'records'} in
                            your list
                        </p>
                        {selected.length === 0 && (
                            <p className="list-empty">
                                A good list starts with one record. Find one in
                                your collection and add it here.
                            </p>
                        )}
                        <DndContext
                            collisionDetection={closestCenter}
                            sensors={sensors}
                            onDragEnd={({ active, over }) => {
                                if (!over || active.id === over.id) return;
                                form.setData(
                                    'selection',
                                    arrayMove(
                                        selected,
                                        selected.findIndex(
                                            (record) =>
                                                record.copyId === active.id,
                                        ),
                                        selected.findIndex(
                                            (record) =>
                                                record.copyId === over.id,
                                        ),
                                    ),
                                );
                            }}
                        >
                            <SortableContext
                                items={selected.map((record) => record.copyId)}
                                strategy={verticalListSortingStrategy}
                            >
                                <ol>
                                    {selected.map((record, index) => (
                                        <SortableListRecord
                                            key={record.copyId}
                                            index={index}
                                            record={record}
                                            onRemove={() =>
                                                form.setData(
                                                    'selection',
                                                    selected.filter(
                                                        (entry) =>
                                                            entry.copyId !==
                                                            record.copyId,
                                                    ),
                                                )
                                            }
                                        />
                                    ))}
                                </ol>
                            </SortableContext>
                        </DndContext>
                        {selected.length >= 1000 && (
                            <p>Lists can contain up to 1,000 records.</p>
                        )}
                    </aside>
                </div>
                <div className="picker-footer">
                    <div className="actions">
                        <Link href={base} className="button secondary">
                            Cancel
                        </Link>
                        <button className="button" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save list'}
                        </button>
                    </div>
                </div>
            </form>
        </>
    );
}
