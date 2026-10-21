import '../../../css/collection-motion.css';

import {
    useDeferredValue,
    useEffect,
    useImperativeHandle,
    useMemo,
    useRef,
} from 'react';
import type { Ref } from 'react';
import { flushSync } from 'react-dom';

import { animateCollectionChange } from '@/components/records/animateCollectionChange';
import type { BrowseState } from '@/components/records/browseState';
import { collectionView } from '@/components/records/collection';
import { CollectionControls } from '@/components/records/CollectionControls';
import { CollectionIndex } from '@/components/records/CollectionIndex';
import { motionScrollBehavior } from '@/components/records/recordMotion';
import { RecordShelf } from '@/components/records/RecordShelf';
import type {
    CollectionMode,
    CollectionRecordSummary,
} from '@/components/records/types';
import type { BrowseUpdate } from '@/components/records/useBrowseNavigation';

export type CollectionBrowserHandle = {
    /** Shows every record by an artist, then moves scroll and focus to the result. */
    showArtist: (artist: string) => void;
};

type CollectionBrowserProps = {
    draggable?: boolean;
    browseBasePath?: string;
    browse: BrowseState;
    onBrowse: (update: BrowseUpdate) => void;
    onSelect: (record: CollectionRecordSummary, source: HTMLElement) => void;
    records: CollectionRecordSummary[];
    transitionRecordId: number | null;
    ref?: Ref<CollectionBrowserHandle>;
};

function resultsAnnouncement(
    count: number,
    query: string,
    activeGroup: string | null,
): string {
    const records = `${count} ${count === 1 ? 'record' : 'records'}`;
    if (query)
        return `${records} found for “${query}”${activeGroup ? ` in ${activeGroup}` : ''}`;
    return activeGroup ? `${activeGroup}: ${records}` : '';
}

export function CollectionBrowser({
    draggable = false,
    browseBasePath,
    browse,
    onBrowse,
    onSelect,
    records,
    transitionRecordId,
    ref,
}: CollectionBrowserProps) {
    const browser = useRef<HTMLElement>(null);
    const controls = useRef<HTMLDivElement>(null);
    const heading = useRef<HTMLHeadingElement>(null);
    const wall = useRef<HTMLDivElement>(null);
    const drag = useRef<{
        x: number;
        y: number;
        left: number;
        top: number;
        moved: boolean;
    } | null>(null);
    const stopAnimation = useRef<(() => void) | null>(null);
    const query = useDeferredValue(browse.query);
    const view = useMemo(
        () =>
            collectionView(records, {
                mode: browse.mode,
                group: browse.group,
                query,
            }),
        [records, browse.mode, browse.group, query],
    );
    const { groups, activeGroup } = view;

    useEffect(() => () => stopAnimation.current?.(), []);

    useEffect(() => {
        if (!controls.current) return;
        const observer = new ResizeObserver((entries) => {
            for (const entry of entries) {
                const height =
                    entry.borderBoxSize[0]?.blockSize ??
                    entry.target.getBoundingClientRect().height;
                setBrowserProperty('--controls-height', height);
            }
        });
        observer.observe(controls.current);
        return () => observer.disconnect();
    }, []);

    useImperativeHandle(ref, () => ({
        showArtist(artist: string) {
            stopAnimation.current?.();
            flushSync(() =>
                onBrowse({
                    mode: 'artist',
                    group: artist,
                    query: '',
                    visibleCount: 25,
                }),
            );
            controls.current?.scrollIntoView({
                behavior: motionScrollBehavior(),
            });
            heading.current?.focus({ preventScroll: true });
        },
    }));

    function setBrowserProperty(name: string, height: number) {
        browser.current?.style.setProperty(name, `${height}px`);
    }

    function scrollToCollection() {
        heading.current?.parentElement?.scrollIntoView({
            behavior: motionScrollBehavior(),
            block: 'start',
        });
    }

    function switchCollection(next: Partial<BrowseState>) {
        stopAnimation.current?.();
        if (
            (next.mode &&
                next.mode === browse.mode &&
                (!next.group || next.group === activeGroup)) ||
            (!next.mode && next.group && next.group === activeGroup)
        ) {
            if (next.group) scrollToCollection();
            return;
        }
        const update = () => {
            flushSync(() => onBrowse({ ...next, visibleCount: 25 }));
            wall.current?.scrollTo({ top: 0, left: 0, behavior: 'instant' });
            if (next.group) scrollToCollection();
        };
        if (!browser.current) {
            update();
            return;
        }
        stopAnimation.current = animateCollectionChange(
            browser.current,
            update,
        );
    }

    function changeQuery(nextQuery: string) {
        stopAnimation.current?.();
        wall.current?.scrollTo({ top: 0, left: 0, behavior: 'instant' });
        onBrowse({ query: nextQuery, group: null, visibleCount: 25 });
    }

    function selectRecord(
        record: CollectionRecordSummary,
        source: HTMLElement,
    ) {
        stopAnimation.current?.();
        onSelect(record, source);
    }

    return (
        <section
            aria-label="Browse record collection"
            className="collection-browser"
            ref={browser}
        >
            <CollectionControls
                mode={browse.mode}
                onMode={(mode: CollectionMode) =>
                    switchCollection({ mode, group: null })
                }
                onQuery={changeQuery}
                query={browse.query}
                ref={controls}
            />
            <div
                className={`collection-layout ${browse.mode !== 'recent' ? 'has-index' : 'without-index'}`}
            >
                {browse.mode !== 'recent' && (
                    <CollectionIndex
                        browseBasePath={browseBasePath}
                        activeGroup={activeGroup}
                        groups={groups}
                        key={browse.mode}
                        mode={browse.mode}
                        onGroup={(group) => switchCollection({ group })}
                        query={query}
                    />
                )}
                <div
                    className={`collection-main${draggable ? ' is-draggable' : ''}`}
                    ref={wall}
                    role={draggable ? 'region' : undefined}
                    aria-label={
                        draggable
                            ? 'Record wall. Drag to explore, or use arrow keys.'
                            : undefined
                    }
                    tabIndex={draggable ? 0 : undefined}
                    onPointerDown={(event) => {
                        drag.current = null;
                        if (
                            !draggable ||
                            event.pointerType !== 'mouse' ||
                            event.button !== 0
                        )
                            return;
                        if ((event.target as HTMLElement).closest('a, input'))
                            return;
                        drag.current = {
                            x: event.clientX,
                            y: event.clientY,
                            left: event.currentTarget.scrollLeft,
                            top: event.currentTarget.scrollTop,
                            moved: false,
                        };
                    }}
                    onPointerMove={(event) => {
                        const start = drag.current;
                        if (!start || event.buttons !== 1) return;
                        const x = event.clientX - start.x;
                        const y = event.clientY - start.y;
                        if (!start.moved && Math.hypot(x, y) < 8) return;
                        start.moved = true;
                        event.preventDefault();
                        event.currentTarget.dataset.dragging = 'true';
                        event.currentTarget.setPointerCapture(event.pointerId);
                        event.currentTarget.scrollLeft = start.left - x;
                        event.currentTarget.scrollTop = start.top - y;
                    }}
                    onPointerUp={(event) => {
                        delete event.currentTarget.dataset.dragging;
                        if (
                            event.currentTarget.hasPointerCapture(
                                event.pointerId,
                            )
                        )
                            event.currentTarget.releasePointerCapture(
                                event.pointerId,
                            );
                    }}
                    onPointerCancel={(event) => {
                        drag.current = null;
                        delete event.currentTarget.dataset.dragging;
                    }}
                    onClickCapture={(event) => {
                        if (event.detail && drag.current?.moved) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                    }}
                >
                    {(activeGroup || query) && (
                        <div className="collection-heading">
                            <h2 ref={heading} tabIndex={-1}>
                                {query
                                    ? `“${query}”${activeGroup ? ` / ${activeGroup}` : ''}`
                                    : activeGroup}
                            </h2>
                        </div>
                    )}
                    <RecordShelf
                        browseBasePath={browseBasePath}
                        onArtist={(artist) =>
                            switchCollection({
                                mode: 'artist',
                                group: artist,
                                query: '',
                            })
                        }
                        groupBy={
                            browse.mode === 'recent'
                                ? 'added'
                                : browse.mode === 'decade'
                                  ? 'year'
                                  : null
                        }
                        showYears={browse.mode === 'artist'}
                        onMore={() =>
                            onBrowse((current) => ({
                                ...current,
                                visibleCount: current.visibleCount + 25,
                            }))
                        }
                        onReset={() => changeQuery('')}
                        onSelect={selectRecord}
                        records={view.records}
                        transitionRecordId={transitionRecordId}
                        visibleCount={browse.visibleCount}
                    />
                </div>
            </div>
            <p className="sr-only" role="status">
                {resultsAnnouncement(view.records.length, query, activeGroup)}
            </p>
        </section>
    );
}
