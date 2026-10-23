import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { registerHooks } from 'node:module';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';

registerHooks({
    resolve(specifier, context, nextResolve) {
        const url = specifier.startsWith('@/')
            ? new URL(
                  `../../resources/js/${specifier.slice(2)}`,
                  import.meta.url,
              )
            : specifier.startsWith('.')
              ? new URL(specifier, context.parentURL)
              : null;

        if (url) {
            for (const extension of ['.ts', '.tsx', '/index.ts']) {
                if (existsSync(fileURLToPath(url) + extension)) {
                    return nextResolve(url.href + extension, context);
                }
            }
        }

        return nextResolve(specifier, context);
    },
    load(url, context, nextLoad) {
        if (url.endsWith('.css')) {
            return { format: 'module', shortCircuit: true, source: '' };
        }
        if (url.endsWith('.tsx')) {
            return {
                format: 'module',
                shortCircuit: true,
                source: ts.transpileModule(readFileSync(new URL(url), 'utf8'), {
                    compilerOptions: {
                        jsx: ts.JsxEmit.ReactJSX,
                        module: ts.ModuleKind.ESNext,
                    },
                }).outputText,
            };
        }

        return nextLoad(url, context);
    },
});

const { RecordLinerNotes } =
    await import('../../resources/js/components/records/RecordLinerNotes.tsx');
const { RecordDetails } =
    await import('../../resources/js/components/records/RecordDetails.tsx');
const { discogsText, safeExternalUrl, bandcampLink } =
    await import('../../resources/js/components/records/discogsText.ts');

const record = {
    id: 123,
    artist: 'Oren Ambarchi',
    artists: ['Oren Ambarchi'],
    title: 'Shebang',
    displayTitle: 'Shebang',
    format: 'Vinyl',
    year: 2024,
    originalYear: 2022,
    label: 'Drag City',
    catalogNumber: 'DC859',
    genres: ['Electronic'],
    addedAt: '',
    tracks: [],
    trackTitles: [],
    artistBios: [
        {
            id: 1,
            name: 'Oren Ambarchi',
            profile:
                'First line.\n\nSecond paragraph with [b]bold [i]italic[/i][/b] and [a123].',
            discogsUrl: 'javascript:alert(1)',
        },
    ],
    releaseNotes: 'See [r456] on [l789] and [a=Jim & Jane].',
    credits: {
        release: [
            {
                id: 1,
                name: 'Oren Ambarchi',
                anv: 'O. Ambarchi',
                role: 'Guitar',
                tracks: '1, 2',
            },
        ],
        tracks: [
            {
                position: 'A1',
                title: 'I',
                artists: [{ name: 'Joe Talia', role: 'Drums' }],
            },
        ],
    },
};
const renderNotes = (value) =>
    renderToStaticMarkup(createElement(RecordLinerNotes, { record: value }));
const renderDetails = (value) =>
    renderToStaticMarkup(
        createElement(RecordDetails, {
            record: value,
            details: value,
            titleId: 'release-title',
            onArtist() {},
        }),
    );

await test('three independent, initially open native disclosures render biographies, notes and both credit levels', () => {
    const html = renderNotes(record);
    assert.equal((html.match(/<details open="">/g) ?? []).length, 3);
    assert.match(html, /<summary>About the artist/);
    assert.match(html, /<summary>Release notes/);
    assert.match(html, /<summary>Credits/);
    assert.match(
        html,
        /First line\.<\/p><p class="discogs-text">Second paragraph/,
    );
    assert.match(html, /<strong>bold <em>italic<\/em><\/strong>/);
    assert.match(html, /href="https:\/\/www.discogs.com\/artist\/1"/);
    assert.match(html, /O\. Ambarchi/);
    assert.match(html, /Tracks: 1, 2/);
    assert.match(html, /A1 · I/);
    assert.match(html, /Joe Talia/);
    assert.doesNotMatch(html, /href="javascript:/);
    const details = renderDetails(record);
    assert.match(details, /Released 2022/);
});

await test('old payloads and empty content have no disclosure rows; each content type works alone', () => {
    assert.equal(renderNotes({}), '');
    assert.equal(
        renderNotes({
            artistBios: [{ profile: ' \n ' }],
            releaseNotes: '  ',
            credits: { release: [], tracks: [{ artists: [] }] },
        }),
        '',
    );

    for (const content of [
        { artistBios: record.artistBios },
        { releaseNotes: record.releaseNotes },
        { credits: { release: [], tracks: record.credits.tracks } },
        { credits: { release: record.credits.release, tracks: [] } },
    ]) {
        assert.equal(
            (renderNotes(content).match(/<details open="">/g) ?? []).length,
            1,
        );
    }
});

await test('Discogs markup uses fixed safe links and unknown or malformed markup remains escaped text', () => {
    const html = renderToStaticMarkup(
        createElement(
            'p',
            null,
            discogsText(
                '[a123] [r456] [l789] [a=Jim & Jane] [a=javascript:alert(1)] <script>alert(1)</script> [url=javascript:evil]click[/url] [b]unclosed [/i]',
            ),
        ),
    );
    assert.match(html, /discogs.com\/artist\/123/);
    assert.match(html, /discogs.com\/release\/456/);
    assert.match(html, /discogs.com\/label\/789/);
    assert.match(html, /type=artist&amp;q=Jim%20%26%20Jane/);
    assert.match(html, /&lt;script&gt;/);
    assert.match(html, /\[url=javascript:evil\]click\[\/url\]/);
    assert.match(html, /\[b\]unclosed \[\/i\]/);
    assert.doesNotMatch(html, /href="javascript:|<script>/);

    for (const url of [
        'javascript:alert(1)',
        'data:text/html,evil',
        '//evil.test',
        '/relative',
        'not a URL',
        'java\nscript:alert(1)',
    ]) {
        assert.equal(safeExternalUrl(url), undefined);
    }
    assert.equal(
        safeExternalUrl('https://example.test/album'),
        'https://example.test/album',
    );
    assert.equal(
        safeExternalUrl('http://example.test/album'),
        'http://example.test/album',
    );
});

await test('Bandcamp uses a safe album-search fallback for old or unsafe payloads and accessible Find/Open labels', () => {
    const fallback = bandcampLink(record);
    assert.equal(fallback.search, true);
    assert.equal(
        new URL(fallback.url).searchParams.get('q'),
        'Oren Ambarchi Shebang',
    );
    assert.equal(new URL(fallback.url).searchParams.get('item_type'), 'a');
    assert.match(
        renderDetails(record),
        /aria-label="Find this release on Bandcamp"/,
    );
    assert.match(
        renderDetails({
            ...record,
            bandcampUrl: 'https://artist.bandcamp.com/album/shebang',
            bandcampSearch: false,
        }),
        /aria-label="Open this release on Bandcamp"/,
    );
    assert.match(
        renderDetails({
            ...record,
            bandcampUrl: 'https://bandcamp.com/search?q=Shebang',
            bandcampSearch: true,
        }),
        /aria-label="Find this release on Bandcamp"/,
    );
    assert.deepEqual(
        bandcampLink({
            ...record,
            bandcampUrl: 'javascript:alert(1)',
            bandcampSearch: false,
        }),
        fallback,
    );
});
