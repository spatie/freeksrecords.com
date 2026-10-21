import { ArrowDown, ArrowRight, ArrowUp, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { browseHash } from '@/components/records/browseState';
import { motionScrollBehavior } from '@/components/records/recordMotion';
import type {
    CollectionGroup,
    CollectionMode,
} from '@/components/records/types';
import { collection } from '@/routes';

type IndexMode = Exclude<CollectionMode, 'recent'>;

type CollectionIndexProps = {
    browseBasePath?: string;
    groupHref?: (group: string) => string;
    mode: IndexMode;
    query: string;
    groups: CollectionGroup[];
    activeGroup: string | null;
    onGroup: (group: string) => void;
};

const labels: Record<IndexMode, { heading: string; plural: string }> = {
    decade: { heading: 'Decades', plural: 'decades' },
    genre: { heading: 'Genres', plural: 'genres' },
    artist: { heading: 'Artists', plural: 'artists' },
};

/**
 * The list of decades, genres or artists next to the shelf. Render it with
 * `key={mode}` so the artist filter resets when switching modes.
 */
export function CollectionIndex({
    browseBasePath = collection.url(),
    groupHref,
    mode,
    query,
    groups,
    activeGroup,
    onGroup,
}: CollectionIndexProps) {
    const indexGroups = useRef<HTMLDivElement>(null);
    const [groupQuery, setGroupQuery] = useState('');
    const [indexScroll, setIndexScroll] = useState({
        overflowing: false,
        more: false,
        horizontal: false,
    });
    const displayedGroups = groups.filter((group) =>
        group.name.toLocaleLowerCase().includes(groupQuery.toLocaleLowerCase()),
    );

    useEffect(() => {
        const index = indexGroups.current;
        const selected = index?.querySelector<HTMLElement>(
            '[aria-current="true"]',
        );
        if (!index || !selected) return;
        if (index.scrollHeight > index.clientHeight)
            index.scrollTo({
                top:
                    index.scrollTop +
                    selected.getBoundingClientRect().top -
                    index.getBoundingClientRect().top -
                    index.clientHeight / 3,
                behavior: motionScrollBehavior(),
            });
    }, [activeGroup]);

    useEffect(() => {
        const index = indexGroups.current;
        if (!index) return;
        const update = () => {
            const horizontal = getComputedStyle(index).overflowY === 'hidden';
            const size = horizontal ? index.clientWidth : index.clientHeight;
            const extent = horizontal ? index.scrollWidth : index.scrollHeight;
            const position = horizontal ? index.scrollLeft : index.scrollTop;
            const overflowing = extent > size + 2;
            const more = position + size < extent - 2;
            setIndexScroll((previous) =>
                previous.overflowing === overflowing &&
                previous.more === more &&
                previous.horizontal === horizontal
                    ? previous
                    : { overflowing, more, horizontal },
            );
        };
        const observer = new ResizeObserver(update);
        observer.observe(index);
        for (const child of index.children) observer.observe(child);
        index.addEventListener('scroll', update, { passive: true });
        update();
        return () => {
            observer.disconnect();
            index.removeEventListener('scroll', update);
        };
    }, [groups, groupQuery]);

    function scrollIndex() {
        const index = indexGroups.current;
        if (!index) return;
        const distance = indexScroll.horizontal
            ? index.clientWidth
            : index.clientHeight;
        const position = indexScroll.horizontal
            ? index.scrollLeft
            : index.scrollTop;
        index.scrollTo({
            [indexScroll.horizontal ? 'left' : 'top']: indexScroll.more
                ? position + distance * 0.7
                : 0,
            behavior: motionScrollBehavior(),
        });
    }

    return (
        <aside className="collection-index" aria-label={`${mode} collections`}>
            <div className="index-heading">
                {labels[mode].heading}
                <span>{groups.length}</span>
            </div>
            {mode === 'artist' && (
                <div className="artist-search">
                    <Search aria-hidden="true" size={15} />
                    <input
                        aria-label="Filter artists"
                        className="artist-filter"
                        onChange={(event) => setGroupQuery(event.target.value)}
                        placeholder="Find an artist"
                        type="search"
                        value={groupQuery}
                    />
                    {groupQuery && (
                        <button
                            type="button"
                            aria-label="Clear artist search"
                            onClick={() => setGroupQuery('')}
                        >
                            <X aria-hidden="true" size={16} />
                        </button>
                    )}
                </div>
            )}
            <div
                className="index-groups"
                id="collection-index-groups"
                ref={indexGroups}
                data-more={indexScroll.more}
                data-horizontal={indexScroll.horizontal}
            >
                {displayedGroups.length === 0 && (
                    <p className="index-empty">No matching artists.</p>
                )}
                {displayedGroups.map((group) => (
                    <a
                        className="index-group"
                        aria-current={
                            activeGroup === group.name ? 'true' : undefined
                        }
                        href={
                            groupHref?.(group.name) ??
                            `${browseBasePath}${browseHash({ mode, group: group.name, query, visibleCount: 25 })}`
                        }
                        key={group.name}
                        onClick={(event) => {
                            if (
                                event.metaKey ||
                                event.ctrlKey ||
                                event.shiftKey ||
                                event.altKey
                            )
                                return;
                            event.preventDefault();
                            onGroup(group.name);
                        }}
                    >
                        <span>{group.name}</span>
                        <span>{group.count}</span>
                    </a>
                ))}
            </div>
            {indexScroll.overflowing && (
                <button
                    className="index-continuation"
                    type="button"
                    aria-controls="collection-index-groups"
                    onClick={scrollIndex}
                >
                    {indexScroll.more
                        ? `More ${labels[mode].plural}`
                        : 'Back to start'}
                    {indexScroll.more ? (
                        indexScroll.horizontal ? (
                            <ArrowRight size={14} />
                        ) : (
                            <ArrowDown size={14} />
                        )
                    ) : (
                        <ArrowUp size={14} />
                    )}
                </button>
            )}
        </aside>
    );
}
