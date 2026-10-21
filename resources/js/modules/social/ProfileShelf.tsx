import { InfiniteScroll, router, usePage } from '@inertiajs/react';
import {
    Fragment,
    useContext,
    useEffect,
    useEffectEvent,
    useRef,
    useState,
} from 'react';
import {
    browseFromHash,
    browseHash,
    type BrowseState,
} from '@/components/records/browseState';
import { CollectionBrowser } from '@/components/records/CollectionBrowser';
import { CollectionControls } from '@/components/records/CollectionControls';
import { CollectionIndex } from '@/components/records/CollectionIndex';
import { formatReleaseDate } from '@/components/records/collection';
import { motionScrollBehavior } from '@/components/records/recordMotion';
import { RecordSleeve } from '@/components/records/RecordSleeve';
import type { CollectionRecordSummary } from '@/components/records/types';
import type { BrowseUpdate } from '@/components/records/useBrowseNavigation';
import { RecordRoom } from '@/modules/social/RecordRoom';
import { RecordOverlayContext } from '@/modules/social/RecordOverlay';
import type { ProfileProps, SocialProps } from '@/modules/social/types';

export function ProfileShelf({
    records,
    groups,
    browse,
}: Pick<ProfileProps, 'records' | 'groups' | 'browse'>) {
    const page = usePage<SocialProps>();
    const openRecord = useContext(RecordOverlayContext);
    const wall = useRef<HTMLDivElement>(null);
    const items = useRef<HTMLDivElement>(null);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const cancelSearch = useRef<(() => void) | null>(null);
    const [query, setQuery] = useState(browse.query);
    const [activeGroup, setActiveGroup] = useState<string | null>(browse.group);
    const basePath = page.url.split(/[?#]/)[0];
    const hasIndex = browse.mode !== 'recent' && groups.length > 0;

    useEffect(() => setQuery(browse.query), [browse.query]);
    useEffect(() => {
        const container = wall.current;
        if (!container) return;
        const update = () => {
            const headings = Array.from(
                container.querySelectorAll<HTMLElement>(
                    '[data-collection-section]',
                ),
            );
            const top = container.getBoundingClientRect().top + 90;
            const heading =
                headings.findLast(
                    (element) => element.getBoundingClientRect().top <= top,
                ) ?? headings[0];
            setActiveGroup(heading?.dataset.collectionSection ?? null);
        };
        container.addEventListener('scroll', update, { passive: true });
        update();
        return () => container.removeEventListener('scroll', update);
    }, [records?.data]);
    useEffect(
        () => () => {
            if (searchTimer.current) clearTimeout(searchTimer.current);
            cancelSearch.current?.();
        },
        [],
    );

    function scrollToGroup(group: string | null, smooth: boolean) {
        const container = wall.current;
        if (!container) return false;
        const heading = Array.from(
            container.querySelectorAll<HTMLElement>(
                '[data-collection-section]',
            ),
        ).find((element) => element.dataset.collectionSection === group);
        if (group && !heading) return false;
        container.scrollTo({
            top: heading
                ? container.scrollTop +
                  heading.getBoundingClientRect().top -
                  container.getBoundingClientRect().top
                : 0,
            behavior: smooth ? motionScrollBehavior() : 'instant',
        });
        heading?.focus({ preventScroll: true });
        return true;
    }

    function visit(next: ProfileProps['browse']) {
        if (searchTimer.current) clearTimeout(searchTimer.current);
        cancelSearch.current?.();
        router.get(
            basePath,
            { mode: next.mode, q: next.query, group: next.group ?? '' },
            {
                only: ['records', 'groups', 'browse'],
                reset: ['records'],
                preserveState: true,
                preserveScroll: true,
                onCancelToken: (token) => {
                    cancelSearch.current = token.cancel;
                },
                onSuccess: () =>
                    requestAnimationFrame(() =>
                        scrollToGroup(next.group, false),
                    ),
            },
        );
    }

    const restoreSection = useEffectEvent(() => {
        if (window.location.hash) visit(browseFromHash(window.location.hash));
        else scrollToGroup(browse.group, false);
    });

    useEffect(() => {
        restoreSection();
    }, []);

    return (
        <RecordRoom>
            <CollectionControls
                mode={browse.mode}
                query={query}
                onMode={(mode) => visit({ mode, query, group: null })}
                onQuery={(value) => {
                    setQuery(value);
                    cancelSearch.current?.();
                    if (searchTimer.current) clearTimeout(searchTimer.current);
                    searchTimer.current = setTimeout(
                        () => visit({ ...browse, query: value, group: null }),
                        250,
                    );
                }}
            />
            <div className={`collection-layout ${hasIndex ? 'has-index' : ''}`}>
                {hasIndex && (
                    <CollectionIndex
                        key={browse.mode}
                        mode={browse.mode as 'artist' | 'genre' | 'decade'}
                        query={query}
                        groups={groups}
                        activeGroup={activeGroup}
                        browseBasePath={basePath}
                        groupHref={(group) =>
                            `${basePath}?${new URLSearchParams({ mode: browse.mode, q: query, group })}`
                        }
                        onGroup={(group) => {
                            if (scrollToGroup(group, true)) {
                                router.replace({
                                    url: `${basePath}?${new URLSearchParams({ mode: browse.mode, q: query, group })}`,
                                    props: {
                                        ...page.props,
                                        browse: { ...browse, group },
                                    },
                                    preserveState: true,
                                    preserveScroll: true,
                                });
                                return;
                            }
                            visit({ ...browse, query, group });
                        }}
                    />
                )}
                <div
                    className="collection-main collection-stream"
                    ref={wall}
                    role="region"
                    scroll-region=""
                    tabIndex={0}
                    aria-label="Scrollable record collection"
                >
                    <InfiniteScroll
                        data="records"
                        preserveUrl
                        buffer={300}
                        itemsElement={items}
                        params={{
                            data: {
                                mode: browse.mode,
                                q: browse.query,
                                group: '',
                            },
                        }}
                        loading={
                            <p className="collection-loading" role="status">
                                More records coming in…
                            </p>
                        }
                    >
                        <div className="catalog-grid" ref={items}>
                            {records?.data.map((record, index) => (
                                <Fragment key={record.key}>
                                    {(index === 0 ||
                                        records.data[index - 1].section !==
                                            record.section) && (
                                        <h2
                                            className="collection-section"
                                            data-collection-section={
                                                record.section
                                            }
                                            tabIndex={-1}
                                        >
                                            {browse.mode === 'recent'
                                                ? formatReleaseDate(
                                                      record.section,
                                                  )
                                                : record.section}
                                        </h2>
                                    )}
                                    <RecordSleeve
                                        record={record}
                                        browseBasePath={basePath}
                                        showYear
                                        transitionRecordId={
                                            page.props.recordOverlay?.record
                                                .payload.instanceId ?? null
                                        }
                                        onSelect={(source) =>
                                            openRecord(record.shareUrl, source)
                                        }
                                        onArtist={(artist) =>
                                            visit({
                                                mode: 'artist',
                                                group: artist,
                                                query: '',
                                            })
                                        }
                                    />
                                </Fragment>
                            ))}
                        </div>
                    </InfiniteScroll>
                    {records?.data.length === 0 && (
                        <div className="empty-shelf">
                            <h2>No records found.</h2>
                            <button
                                type="button"
                                onClick={() =>
                                    visit({ ...browse, query: '', group: null })
                                }
                            >
                                Clear search
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </RecordRoom>
    );
}

export function ImportedShelf({
    records,
}: {
    records: CollectionRecordSummary[];
}) {
    const page = usePage<SocialProps & { socialBrowse?: BrowseState }>();
    const openRecord = useContext(RecordOverlayContext);
    const basePath = page.url.split(/[?#]/)[0];
    const hash =
        typeof window === 'undefined'
            ? (page.url.split('#')[1] ?? '')
            : window.location.hash;
    const browse =
        page.props.socialBrowse &&
        browseHash(page.props.socialBrowse) === browseHash(browseFromHash(hash))
            ? page.props.socialBrowse
            : browseFromHash(hash);
    function changeBrowse(update: BrowseUpdate) {
        const next =
            typeof update === 'function'
                ? update(browse)
                : { ...browse, ...update };
        const options = {
            url: `${basePath}${browseHash(next)}`,
            props: { ...page.props, socialBrowse: next },
            preserveState: true,
            preserveScroll: true,
        };
        if (
            browse.query !== next.query ||
            browseHash(browse) === browseHash(next)
        )
            router.replace(options);
        else router.push(options);
    }
    return (
        <RecordRoom>
            <CollectionBrowser
                draggable
                browse={browse}
                browseBasePath={basePath}
                onBrowse={changeBrowse}
                onSelect={(record, source) => {
                    openRecord(record.shareUrl, source);
                }}
                records={records}
                transitionRecordId={
                    page.props.recordOverlay?.record.payload.instanceId ?? null
                }
            />
        </RecordRoom>
    );
}
