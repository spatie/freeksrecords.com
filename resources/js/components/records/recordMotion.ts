export type SleeveOrigin = {
    x: number;
    y: number;
    width: number;
    rotation: number;
};

export function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

export function motionScrollBehavior(): ScrollBehavior {
    return prefersReducedMotion() ? 'instant' : 'smooth';
}

export function sleeveOrigin(instanceId: number): SleeveOrigin | null {
    const sleeve = document.querySelector<HTMLElement>(
        `[data-record-id="${instanceId}"] .catalog-record-art`,
    );
    if (!sleeve) return null;

    return artworkOrigin(sleeve);
}

export function artworkOrigin(sleeve: HTMLElement): SleeveOrigin | null {
    const bounds = sleeve.getBoundingClientRect();
    if (bounds.bottom <= 0 || bounds.top >= window.innerHeight) return null;
    let scale = 1;
    let rotation = 0;
    for (
        let element: HTMLElement | null = sleeve;
        element;
        element = element.parentElement
    ) {
        const style = getComputedStyle(element);
        const matrix = new DOMMatrixReadOnly(
            style.transform === 'none' ? undefined : style.transform,
        );
        scale *=
            Math.hypot(matrix.a, matrix.b) *
            (style.scale === 'none' ? 1 : parseFloat(style.scale));
        rotation += (Math.atan2(matrix.b, matrix.a) * 180) / Math.PI;
    }
    return {
        x: bounds.left + bounds.width / 2,
        y: bounds.top + bounds.height / 2,
        width: sleeve.offsetWidth * scale,
        rotation,
    };
}

export function originTransform(origin: SleeveOrigin, target: DOMRect): string {
    return `translate(${origin.x - target.left - target.width / 2}px, ${origin.y - target.top - target.height / 2}px) scale(${origin.width / target.width}) rotate(${origin.rotation}deg)`;
}
