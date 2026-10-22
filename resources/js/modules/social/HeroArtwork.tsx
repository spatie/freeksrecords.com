import { usePage } from '@inertiajs/react';
import { useEffect, useRef, type CSSProperties } from 'react';

import { RecordLink } from '@/modules/social/RecordOverlay';
import type { RecordArtwork, SocialProps } from '@/modules/social/types';

export function HeroArtwork({
    records,
    moving = false,
}: {
    records: RecordArtwork[];
    moving?: boolean;
}) {
    const artwork = useRef<HTMLDivElement>(null);
    const opened = Boolean(usePage<SocialProps>().props.recordOverlay);
    const updateOverlay = useRef<((opened: boolean) => void) | null>(null);
    const artworkKey = JSON.stringify(records.map((record) => record.cover));

    useEffect(() => {
        const element = artwork.current;
        const belt = element?.closest<HTMLElement>('.landing-records');
        if (!moving || !element || !belt) return;

        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const animation = element.animate(
            [{ transform: 'translateX(0)' }, { transform: 'translateX(-50%)' }],
            { duration: 60_000, iterations: Infinity },
        );
        let overlayOpen = false;
        let returning = false;
        let restoredFocus: Element | null = null;
        let timer: ReturnType<typeof setTimeout> | undefined;
        let frame = 0;
        let disposed = false;

        const cancelResume = () => {
            clearTimeout(timer);
            cancelAnimationFrame(frame);
        };
        const synchronize = () => {
            cancelResume();
            const focused = document.activeElement;
            const blocked =
                reducedMotion.matches ||
                overlayOpen ||
                belt.matches(':hover') ||
                (belt.contains(focused) && focused !== restoredFocus);
            if (blocked) {
                animation.pause();
                return;
            }
            if (!returning) {
                animation.playbackRate = 1;
                animation.play();
                return;
            }

            animation.pause();
            timer = setTimeout(() => {
                animation.playbackRate = 0;
                animation.play();
                const start = performance.now();
                const accelerate = (now: number) => {
                    const progress = Math.min((now - start) / 3000, 1);
                    // Smoothstep joins both the still and full-speed states gently.
                    animation.playbackRate =
                        progress * progress * (3 - 2 * progress);
                    if (progress < 1) frame = requestAnimationFrame(accelerate);
                    else returning = false;
                };
                frame = requestAnimationFrame(accelerate);
            }, 2500);
        };
        updateOverlay.current = (nextOpened) => {
            if (overlayOpen && !nextOpened) {
                returning = true;
                // Only the overlay's restored focus is exempt, not later navigation.
                restoredFocus = belt.contains(document.activeElement)
                    ? document.activeElement
                    : null;
            }
            overlayOpen = nextOpened;
            synchronize();
        };
        const onFocus = () => {
            if (!overlayOpen) restoredFocus = null;
            synchronize();
        };
        const onBlur = () => {
            queueMicrotask(() => {
                if (!disposed) synchronize();
            });
        };
        belt.addEventListener('mouseenter', synchronize);
        belt.addEventListener('mouseleave', synchronize);
        belt.addEventListener('focusin', onFocus);
        belt.addEventListener('focusout', onBlur);
        reducedMotion.addEventListener('change', synchronize);
        synchronize();

        return () => {
            disposed = true;
            updateOverlay.current = null;
            cancelResume();
            animation.cancel();
            belt.removeEventListener('mouseenter', synchronize);
            belt.removeEventListener('mouseleave', synchronize);
            belt.removeEventListener('focusin', onFocus);
            belt.removeEventListener('focusout', onBlur);
            reducedMotion.removeEventListener('change', synchronize);
        };
    }, [moving, artworkKey]);

    useEffect(() => {
        updateOverlay.current?.(opened);
    }, [opened, moving, artworkKey]);

    useEffect(() => {
        if (!moving) return;
        const hero = artwork.current?.closest<HTMLElement>('.landing-hero');
        if (!hero) return;
        const positionBelt = () =>
            hero.style.setProperty(
                '--belt-angle',
                `${(-Math.atan2(hero.clientHeight * 0.38, hero.clientWidth) * 180) / Math.PI}deg`,
            );
        const observer = new ResizeObserver(positionBelt);
        observer.observe(hero);
        positionBelt();
        return () => observer.disconnect();
    }, [moving]);

    useEffect(() => {
        const element = artwork.current;
        if (!element) return;
        let cancelled = false;
        void Promise.allSettled(
            Array.from(element.querySelectorAll('img'), (image) =>
                image.decode(),
            ),
        ).then(() => {
            if (!cancelled) element.dataset.ready = 'true';
        });
        return () => {
            cancelled = true;
        };
    }, [artworkKey]);

    if (!records.length) return null;

    return (
        <div className="hero-artwork" key={artworkKey} ref={artwork}>
            {Array.from({ length: moving ? 2 : 1 }, (_, run) => (
                <div className="artwork-run" key={run}>
                    {records.map((record, index) => (
                        <div
                            className="floating-sleeve"
                            key={record.url}
                            style={{ '--sleeve-order': index } as CSSProperties}
                        >
                            <RecordLink href={record.url}>
                                <img
                                    src={record.cover}
                                    alt={record.title}
                                    decoding="async"
                                    width="240"
                                    height="240"
                                />
                            </RecordLink>
                        </div>
                    ))}
                </div>
            ))}
        </div>
    );
}
