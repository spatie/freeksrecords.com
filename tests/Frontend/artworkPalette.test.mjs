import assert from 'node:assert/strict';
import test from 'node:test';

import { paletteFromPixels } from '../../resources/js/components/records/artworkLabel.ts';

const pixels = (...colors) =>
    new Uint8ClampedArray(
        colors.flatMap((color) =>
            Array.from({ length: 64 }, () => [...color, 255]).flat(),
        ),
    );
const luminance = (color) =>
    color
        .map((channel) => channel / 255)
        .map((channel) =>
            channel <= 0.04045
                ? channel / 12.92
                : ((channel + 0.055) / 1.055) ** 2.4,
        )
        .reduce(
            (total, channel, index) =>
                total + channel * [0.2126, 0.7152, 0.0722][index],
            0,
        );

await test('cover palettes keep distinct dominant hues instead of adjacent shades', () => {
    const { primary, secondary } = paletteFromPixels(
        pixels([220, 60, 40], [210, 55, 45], [30, 90, 200]),
    );
    assert.ok(primary[0] > primary[2]);
    assert.ok(secondary[2] > secondary[0]);
});

await test('dark, monochrome and nearly identical covers still produce readable, distinct surfaces', () => {
    for (const colors of [
        [[0, 0, 0]],
        [[255, 255, 255]],
        [[127, 127, 127]],
        [[180, 181, 179]],
        [[220, 40, 30]],
        [
            [30, 50, 80],
            [32, 52, 82],
        ],
        [
            [0, 20, 180],
            [250, 200, 40],
        ],
    ]) {
        const { primary, secondary } = paletteFromPixels(pixels(...colors));
        for (const color of [primary, secondary]) {
            assert.ok(
                (luminance(color) + 0.05) / (luminance([30, 30, 21]) + 0.05) >=
                    5.5,
            );
        }
        assert.ok(
            Math.hypot(
                ...primary.map((channel, index) => channel - secondary[index]),
            ) >= 85,
        );
    }
});
