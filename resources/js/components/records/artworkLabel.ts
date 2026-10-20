export function contrastingInk(
    red: number,
    green: number,
    blue: number,
): string {
    const linear = [red, green, blue].map((channel) => {
        const value = channel / 255;
        return value <= 0.04045
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });
    const luminance =
        linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
    return luminance > 0.179 ? '#000000' : '#ffffff';
}

export function artworkLabelStyle(image: HTMLImageElement): {
    backgroundColor: string;
    color: string;
} {
    const fallback = { backgroundColor: '#97492e', color: '#ffffff' };
    try {
        const [dominant] = dominantColors(readArtworkPixels(image));
        if (!dominant) return fallback;
        return {
            backgroundColor: `rgb(${dominant.join(', ')})`,
            color: contrastingInk(...dominant),
        };
    } catch {
        return fallback;
    }
}

type RGB = [number, number, number];

function readArtworkPixels(image: HTMLImageElement): Uint8ClampedArray {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 32;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) return new Uint8ClampedArray();
    context.drawImage(image, 0, 0, 32, 32);
    return context.getImageData(0, 0, 32, 32).data;
}

function dominantColors(pixels: Uint8ClampedArray): RGB[] {
    const buckets = new Map<
        string,
        {
            count: number;
            red: number;
            green: number;
            blue: number;
            score: number;
        }
    >();
    for (let index = 0; index < pixels.length; index += 4) {
        if (pixels[index + 3] < 128) continue;
        const [red, green, blue] = pixels.slice(index, index + 3);
        const maximum = Math.max(red, green, blue);
        const minimum = Math.min(red, green, blue);
        if (maximum < 35 || minimum > 230) continue;
        const key = [red, green, blue]
            .map((value) => Math.floor(value / 32))
            .join(',');
        const bucket = buckets.get(key) ?? {
            count: 0,
            red: 0,
            green: 0,
            blue: 0,
            score: 0,
        };
        bucket.count++;
        bucket.red += red;
        bucket.green += green;
        bucket.blue += blue;
        bucket.score += 1 + (maximum - minimum) / 80;
        buckets.set(key, bucket);
    }
    const colors = [...buckets.values()];
    const saturated = colors.filter(
        (color) =>
            color.count >= 5 &&
            (Math.max(color.red, color.green, color.blue) -
                Math.min(color.red, color.green, color.blue)) /
                color.count >=
                45,
    );
    return (saturated.length ? saturated : colors)
        .sort((first, second) => second.score - first.score)
        .map((color) => [
            Math.round(color.red / color.count),
            Math.round(color.green / color.count),
            Math.round(color.blue / color.count),
        ]);
}

export function paletteFromPixels(pixels: Uint8ClampedArray): {
    primary: RGB;
    secondary: RGB;
} {
    const colors = dominantColors(pixels);
    const first = colors[0] ?? [55, 49, 75];
    const distance = (left: RGB, right: RGB) =>
        Math.hypot(...left.map((channel, index) => channel - right[index]));
    const second =
        colors.find((color) => distance(first, color) >= 110) ??
        ([255 - first[1], 255 - first[2], 255 - first[0]] as RGB);
    function readable(color: RGB): RGB {
        const luminance = (channels: RGB) =>
            channels
                .map((value) => value / 255)
                .map((value) =>
                    value <= 0.04045
                        ? value / 12.92
                        : ((value + 0.055) / 1.055) ** 2.4,
                )
                .reduce(
                    (sum, value, index) =>
                        sum + value * [0.2126, 0.7152, 0.0722][index],
                    0,
                );
        let result = color;
        // Both diagonal surfaces share dark text, including where text crosses the seam.
        while (
            (luminance(result) + 0.05) / (luminance([30, 30, 21]) + 0.05) <
            5.5
        )
            result = result.map((value) => Math.min(255, value + 8)) as RGB;
        return result;
    }
    const primary = readable(first);
    let secondary = readable(second);
    if (distance(primary, secondary) < 85) {
        // Near-monochrome sleeves get a contrasting tint rather than a second almost-identical grey.
        const tints: RGB[] = [
            [178, 164, 237],
            [221, 237, 139],
            [250, 170, 100],
        ];
        secondary = tints
            .map(readable)
            .sort(
                (left, right) =>
                    distance(primary, right) - distance(primary, left),
            )[0];
    }
    return { primary, secondary };
}

export function artworkThemeStyle(
    image: HTMLImageElement,
): Record<string, string> {
    try {
        const palette = paletteFromPixels(readArtworkPixels(image));
        return {
            '--record-primary': `rgb(${palette.primary.join(', ')})`,
            '--record-secondary': `rgb(${palette.secondary.join(', ')})`,
        };
    } catch {
        return {};
    }
}
