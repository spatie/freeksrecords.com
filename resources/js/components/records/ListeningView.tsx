import { Share2, X } from 'lucide-react';
import {
    useEffect,
    useEffectEvent,
    useId,
    useLayoutEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';

import {
    artworkLabelStyle,
    artworkThemeStyle,
} from '@/components/records/artworkLabel';
import type { SleeveOrigin } from '@/components/records/recordMotion';
import { RecordDetails } from '@/components/records/RecordDetails';
import type {
    CollectionRecord,
    CollectionRecordSummary,
} from '@/components/records/types';
import { useRecordTransition } from '@/components/records/useRecordTransition';
import '../../../css/record-cases.css';

type ListeningViewProps = {
    actions?: ReactNode;
    clubLayout?: boolean;
    returnOrigin?: () => SleeveOrigin | null;
    instant: boolean;
    onBack: () => void;
    onReady?: () => void;
    onArtist: (artist: string) => void;
    origin: SleeveOrigin | null;
    record: CollectionRecordSummary;
    details?: CollectionRecord;
    children?: ReactNode;
};

const focusableSelector = [
    'a[href]',
    'area[href]',
    'button:not(:disabled)',
    'input:not(:disabled):not([type="hidden"])',
    'select:not(:disabled)',
    'textarea:not(:disabled)',
    'iframe',
    'audio[controls]',
    'video[controls]',
    '[contenteditable]:not([contenteditable="false"])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

const phaseClasses = {
    moving: 'is-moving',
    settled: 'is-settled',
    returning: 'is-moving is-returning',
};

function focusableElements(container: HTMLElement): HTMLElement[] {
    return Array.from(
        container.querySelectorAll<HTMLElement>(focusableSelector),
    ).filter((element) => element.getClientRects().length > 0);
}

export function ListeningView({
    actions,
    clubLayout = false,
    returnOrigin,
    instant,
    onBack,
    onReady,
    onArtist,
    origin,
    record,
    details,
    children,
}: ListeningViewProps) {
    const dialog = useRef<HTMLElement>(null);
    const label = useRef<HTMLDivElement>(null);
    const titleId = useId();
    const [shareStatus, setShareStatus] = useState('');
    const mediaFormat = record.mediaFormats?.[0] ?? 'Vinyl';
    const [artworkTheme, setArtworkTheme] = useState<Record<
        string,
        string
    > | null>(clubLayout ? null : {});
    const ready = artworkTheme !== null;
    const { refs, phase, putAway } = useRecordTransition({
        instanceId: record.instanceId,
        mediaFormat,
        clubLayout,
        ready,
        returnOrigin,
        origin,
        instant,
        onSettled: () =>
            document.getElementById(titleId)?.focus({ preventScroll: true }),
        onReturned: onBack,
    });
    const returning = phase === 'returning';
    const motionClasses = `is-ready ${phaseClasses[phase]}`;
    const notifyReady = useEffectEvent(() => {
        onReady?.();
        dialog.current?.focus({ preventScroll: true });
    });

    useLayoutEffect(() => {
        if (ready) notifyReady();
    }, [ready]);

    useEffect(() => {
        if (phase === 'settled' && window.location.hash === '#comments')
            dialog.current?.querySelector('#comments')?.scrollIntoView();
    }, [phase]);

    useEffect(() => {
        if (!shareStatus) return;
        const timeout = window.setTimeout(() => setShareStatus(''), 4000);
        return () => window.clearTimeout(timeout);
    }, [shareStatus]);

    // The rest of the page is inert, so listening on the document keeps Escape and Tab working wherever focus ends up.
    const onKeyDown = useEffectEvent((event: KeyboardEvent) => {
        const container = dialog.current;
        if (!container || returning) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            void putAway();
            return;
        }
        if (event.key !== 'Tab') return;
        const controls = focusableElements(container);
        const first = controls[0];
        const last = controls.at(-1);
        if (!first || !last) {
            event.preventDefault();
            container.focus();
            return;
        }
        const active = document.activeElement;
        const outside =
            !(active instanceof Node) || !container.contains(active);
        const atStart =
            active === first ||
            active === container ||
            (active instanceof HTMLElement && active.id === titleId);
        if (event.shiftKey && (outside || atStart)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (outside || active === last)) {
            event.preventDefault();
            first.focus();
        }
    });

    useEffect(() => {
        const listener = (event: KeyboardEvent) => onKeyDown(event);
        document.addEventListener('keydown', listener);
        return () => document.removeEventListener('keydown', listener);
    }, []);

    async function shareRecord() {
        const url = new URL(record.shareUrl, window.location.origin).href;
        try {
            if (navigator.share)
                await navigator.share({
                    title: `${record.displayTitle} by ${record.artist}`,
                    url,
                });
            else {
                await navigator.clipboard.writeText(url);
                setShareStatus('Link copied');
            }
        } catch (error) {
            if (!(error instanceof DOMException && error.name === 'AbortError'))
                setShareStatus(
                    'Couldn’t share. Copy the link from the address bar.',
                );
        }
    }

    return (
        <div
            className={`record-focus ${motionClasses}`}
            data-media-format={mediaFormat}
            style={{
                ...artworkTheme,
                visibility: ready ? undefined : 'hidden',
            }}
        >
            <button
                ref={refs.backdrop}
                className="record-focus-backdrop"
                aria-label="Close record"
                tabIndex={-1}
                onClick={() => void putAway()}
                type="button"
            />
            <section
                aria-labelledby={titleId}
                ref={dialog}
                tabIndex={-1}
                aria-modal="true"
                role="dialog"
                className={`listening-view ${motionClasses}`}
            >
                <div
                    className="listening-card-surface"
                    ref={refs.surface}
                    aria-hidden="true"
                />
                <button
                    className="listening-share"
                    onClick={() => void shareRecord()}
                    type="button"
                >
                    <Share2 size={17} /> Share
                </button>
                <div role="status">
                    {shareStatus && (
                        <p className="record-share-status">{shareStatus}</p>
                    )}
                </div>
                <button
                    className="listening-close"
                    aria-label="Close record"
                    disabled={returning}
                    onClick={() => void putAway()}
                    type="button"
                >
                    <X size={22} />
                </button>
                <div className="listening-scroll">
                    <div className="listening-layout">
                        <div className="record-stage">
                            <div className="record-stage-art" ref={refs.stage}>
                                <div
                                    aria-hidden="true"
                                    className={
                                        mediaFormat === 'Cassette'
                                            ? 'extracted-cassette'
                                            : mediaFormat === 'CD'
                                              ? 'extracted-cd'
                                              : 'extracted-vinyl'
                                    }
                                    ref={refs.medium}
                                >
                                    {mediaFormat !== 'Cassette' && (
                                        <div className="vinyl-reflection" />
                                    )}
                                    <div
                                        className={
                                            mediaFormat === 'Cassette'
                                                ? 'cassette-label'
                                                : 'record-centre'
                                        }
                                        ref={label}
                                    >
                                        <span>{record.artist}</span>
                                        <strong>{record.displayTitle}</strong>
                                        <span>
                                            {record.originalYear ?? record.year}
                                        </span>
                                        <i />
                                    </div>
                                    {mediaFormat === 'Cassette' && (
                                        <>
                                            <span className="cassette-side">
                                                A
                                            </span>
                                            <div className="cassette-window">
                                                <i className="cassette-reel" />
                                                <i className="cassette-reel" />
                                            </div>
                                            <div className="cassette-head" />
                                        </>
                                    )}
                                </div>
                                <div
                                    className="extracted-sleeve"
                                    ref={refs.sleeve}
                                >
                                    <img
                                        alt={`${record.displayTitle} sleeve`}
                                        onError={() => setArtworkTheme({})}
                                        onLoad={(event) => {
                                            if (clubLayout)
                                                setArtworkTheme(
                                                    artworkThemeStyle(
                                                        event.currentTarget,
                                                    ),
                                                );
                                            if (
                                                label.current &&
                                                mediaFormat !== 'CD'
                                            )
                                                Object.assign(
                                                    label.current.style,
                                                    artworkLabelStyle(
                                                        event.currentTarget,
                                                    ),
                                                );
                                        }}
                                        draggable={false}
                                        height={600}
                                        src={record.cover}
                                        width={600}
                                    />
                                    {mediaFormat === 'Cassette' && (
                                        <span
                                            className="cassette-inlay"
                                            aria-hidden="true"
                                        >
                                            <strong>{record.artist}</strong>
                                            <span>{record.displayTitle}</span>
                                            <small>Cassette</small>
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>
                        <div className="listening-information">
                            <RecordDetails
                                actions={actions}
                                onArtist={onArtist}
                                record={record}
                                titleId={titleId}
                                details={details}
                            />
                            {children}
                            <p className="record-attribution">
                                <a
                                    href={`https://www.discogs.com/release/${record.id}`}
                                    rel="noopener noreferrer"
                                    target="_blank"
                                >
                                    Data provided by Discogs ↗
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}
