import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    createContext,
    useContext,
    useEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';

import { ListeningView } from '@/components/records/ListeningView';
import { browseHash } from '@/components/records/browseState';
import {
    artworkOrigin,
    type SleeveOrigin,
} from '@/components/records/recordMotion';
import { RecordRoom } from '@/modules/social/RecordRoom';
import { RecordSocial } from '@/modules/social/RecordSocial';
import type { SocialProps } from '@/modules/social/types';

export const RecordOverlayContext = createContext<
    (url: string, source?: HTMLElement | null) => void
>((url) => router.visit(url));

export function RecordLink({
    href,
    children,
}: {
    href: string;
    children: ReactNode;
}) {
    const open = useContext(RecordOverlayContext);
    return (
        <Link
            href={href}
            onClick={(event) => {
                if (
                    event.button !== 0 ||
                    event.metaKey ||
                    event.ctrlKey ||
                    event.shiftKey ||
                    event.altKey
                )
                    return;
                event.preventDefault();
                open(href, event.currentTarget as HTMLElement);
            }}
        >
            {children}
        </Link>
    );
}

export function RecordOverlay({ children }: { children: ReactNode }) {
    const overlay = usePage<SocialProps>().props.recordOverlay;
    const [origin, setOrigin] = useState<SleeveOrigin | null>(null);
    const source = useRef<HTMLElement | null>(null);
    const opened = Boolean(overlay);

    useEffect(() => {
        if (!opened) return;
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previous;
            source.current?.focus({ preventScroll: true });
        };
    }, [opened]);

    function open(url: string, element?: HTMLElement | null) {
        source.current = element?.closest<HTMLElement>('a, button') ?? null;
        const artwork =
            element?.closest<HTMLElement>('.floating-sleeve') ??
            element?.querySelector<HTMLElement>('.catalog-record-art') ??
            element;
        setOrigin(artwork ? artworkOrigin(artwork) : null);
        const destination = new URL(window.location.href);
        destination.searchParams.set('record', url.split('/').pop()!);
        destination.searchParams.delete('commentsPage');
        router.visit(destination.href, {
            only: ['recordOverlay'],
            preserveState: true,
            preserveScroll: true,
        });
    }

    function close() {
        const destination = new URL(window.location.href);
        destination.searchParams.delete('record');
        destination.searchParams.delete('commentsPage');
        router.visit(destination.href, {
            only: ['recordOverlay'],
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    }

    return (
        <RecordOverlayContext value={open}>
            <div inert={opened} aria-hidden={opened}>
                {children}
            </div>
            {overlay && (
                <RecordRoom>
                    <Head title={overlay.record.payload.displayTitle} />
                    <ListeningView
                        key={overlay.record.id}
                        instant={origin === null}
                        origin={origin}
                        record={{
                            ...overlay.record.payload,
                            trackTitles: overlay.record.payload.tracks.map(
                                (track) => track.title,
                            ),
                            shareUrl: `/shelves/${overlay.record.id}`,
                        }}
                        details={overlay.record.payload}
                        onBack={close}
                        onArtist={(artist) =>
                            router.visit(
                                `${overlay.record.owner.profileUrl}${browseHash({ mode: 'artist', group: artist, query: '', visibleCount: 25 })}`,
                            )
                        }
                    >
                        <div className="record-social">
                            <RecordSocial {...overlay} />
                        </div>
                    </ListeningView>
                </RecordRoom>
            )}
        </RecordOverlayContext>
    );
}
