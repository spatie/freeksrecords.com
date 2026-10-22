import { useEffectEvent, useLayoutEffect, useRef, useState } from 'react';

import {
    originTransform,
    prefersReducedMotion,
    sleeveOrigin,
} from '@/components/records/recordMotion';
import type { SleeveOrigin } from '@/components/records/recordMotion';

const sleeveRest = 'translate(-4%, 28%) rotate(-7deg) scale(.94)';
const vinylRest = 'translate(18%, -14%) rotate(12deg)';
const restingEase = 'cubic-bezier(.22, 1, .36, 1)';

export type RecordTransitionPhase = 'moving' | 'settled' | 'returning';

type RecordTransitionOptions = {
    instanceId: number;
    mediaFormat?: string;
    origin: SleeveOrigin | null;
    returnOrigin?: () => SleeveOrigin | null;
    clubLayout?: boolean;
    ready?: boolean;
    /** Skip the cover's entrance flight for a record rendered in place. */
    instant: boolean;
    onSettled: () => void;
    onReturned: () => void;
};

/**
 * Animates a record out of its sleeve on the shelf and back again.
 */
export function useRecordTransition({
    instanceId,
    mediaFormat = 'Vinyl',
    origin,
    returnOrigin,
    clubLayout = false,
    ready = true,
    instant,
    onSettled,
    onReturned,
}: RecordTransitionOptions) {
    const stage = useRef<HTMLDivElement>(null);
    const surface = useRef<HTMLDivElement>(null);
    const backdrop = useRef<HTMLButtonElement>(null);
    const sleeve = useRef<HTMLDivElement>(null);
    const medium = useRef<HTMLDivElement>(null);
    const animations = useRef<Animation[]>([]);
    const returning = useRef(false);
    const [phase, setPhase] = useState<RecordTransitionPhase>('moving');
    const settle = useEffectEvent(() => {
        if (returning.current) return;
        setPhase('settled');
        onSettled();
    });

    useLayoutEffect(() => {
        if (!ready) return;
        if (!stage.current || !sleeve.current || !medium.current) return;
        const reducedMotion = prefersReducedMotion();
        const duration = instant || reducedMotion ? 0 : clubLayout ? 650 : 420;
        const caseTransform =
            mediaFormat === 'Cassette'
                ? 'translate(-18%, -6%) rotate(-10deg)'
                : mediaFormat === 'CD'
                  ? 'translate(-10%, 5%) rotate(-7deg)'
                  : clubLayout
                    ? 'translate(-8%, 5%) rotate(-9deg)'
                    : sleeveRest;
        const mediumTransform =
            mediaFormat === 'Cassette'
                ? 'translate(58%, 54%) rotate(8deg) scale(1.35)'
                : mediaFormat === 'CD'
                  ? 'translateX(54%) rotate(140deg)'
                  : clubLayout
                    ? 'translateX(43%) rotate(80deg)'
                    : vinylRest;
        const target = stage.current.getBoundingClientRect();
        const animate = (
            element: HTMLElement | null,
            frames: Keyframe[],
            delay = 0,
            animationDuration = duration,
        ) => {
            if (!element) return;
            animations.current.push(
                element.animate(frames, {
                    duration: Math.max(0, animationDuration - delay),
                    delay: animationDuration === 0 ? 0 : delay,
                    easing: restingEase,
                    fill: 'both',
                }),
            );
        };
        animate(stage.current, [
            {
                transform: origin
                    ? originTransform(origin, target)
                    : 'scale(.97)',
            },
            { transform: 'none' },
        ]);
        animate(sleeve.current, [
            { transform: 'none' },
            { transform: caseTransform },
        ]);
        const mediumDelay = duration === 0 ? 0 : clubLayout ? 300 : 50;
        animate(
            medium.current,
            [
                {
                    transform: 'none',
                    opacity: mediaFormat === 'Cassette' ? 0 : 1,
                },
                { transform: mediumTransform, opacity: 1 },
            ],
            mediumDelay,
            duration > 0 && clubLayout ? mediumDelay + 650 : duration,
        );
        animate(surface.current, [{ opacity: 0 }, { opacity: 1 }]);
        animate(backdrop.current, [{ opacity: 0 }, { opacity: 1 }]);
        let cancelled = false;
        void Promise.allSettled(
            animations.current.map((animation) => animation.finished),
        ).then(() => {
            if (!cancelled) settle();
        });
        return () => {
            cancelled = true;
            animations.current.forEach((animation) => animation.cancel());
            animations.current = [];
        };
    }, [origin, instant, clubLayout, ready, mediaFormat]);

    async function putAway() {
        if (returning.current) return;
        returning.current = true;
        setPhase('returning');
        const duration = prefersReducedMotion() ? 0 : clubLayout ? 500 : 190;
        const elements = [stage.current, sleeve.current, medium.current];
        const transforms = elements.map((element) =>
            element ? getComputedStyle(element).transform : 'none',
        );
        const mediumOpacity = medium.current
            ? getComputedStyle(medium.current).opacity
            : '1';
        const destination = returnOrigin
            ? returnOrigin()
            : (origin ?? sleeveOrigin(instanceId));
        const target = stage.current;
        const opacityElements = [surface.current, backdrop.current];
        const opacities = opacityElements.map((element) =>
            element ? getComputedStyle(element).opacity : '1',
        );
        animations.current.forEach((animation) => animation.cancel());
        animations.current = [];
        const stageBounds = target?.getBoundingClientRect() ?? null;
        elements.forEach((element, index) => {
            if (!element) return;
            animations.current.push(
                element.animate(
                    [
                        {
                            transform: transforms[index],
                            opacity: index === 2 ? mediumOpacity : 1,
                        },
                        {
                            transform:
                                index === 0 && destination && stageBounds
                                    ? originTransform(destination, stageBounds)
                                    : 'none',
                            opacity:
                                (index === 0 && !destination) ||
                                (index === 2 && mediaFormat === 'Cassette')
                                    ? 0
                                    : 1,
                        },
                    ],
                    {
                        duration,
                        easing: 'cubic-bezier(.4, 0, .6, 1)',
                        fill: 'both',
                    },
                ),
            );
        });
        opacityElements.forEach((element, index) => {
            if (element)
                animations.current.push(
                    element.animate(
                        [{ opacity: opacities[index] }, { opacity: 0 }],
                        { duration, fill: 'both' },
                    ),
                );
        });
        await Promise.allSettled(
            animations.current.map((animation) => animation.finished),
        );
        if (target?.isConnected) onReturned();
    }

    return {
        refs: { stage, surface, backdrop, sleeve, medium },
        phase,
        putAway,
    };
}
