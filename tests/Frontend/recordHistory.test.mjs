import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

import { initializeRecordHistory } from '../../resources/js/lib/record-history.ts';

await test('the landing page preserves legacy shelf fragments but leaves other anchors alone', () => {
    const blade = readFileSync(
        new URL(
            '../../resources/views/social/layout.blade.php',
            import.meta.url,
        ),
        'utf8',
    );
    const script = blade.match(/<script>([\s\S]*?)<\/script>/)[1];
    for (const hash of [
        '#record-123',
        '#genre=Blues&q=Live',
        '#artist=Pink+Floyd',
        '#decade=1990s',
        '#q=Live',
        '#how-it-works',
        '#main',
        '#record-nope',
        '',
    ]) {
        let destination = null;
        runInNewContext(script, {
            window: {
                location: {
                    hash,
                    replace: (url) => {
                        destination = url;
                    },
                },
            },
        });
        const legacy = /^#record-\d+$|^#(?:genre|artist|decade|q)=/.test(hash);
        assert.equal(destination, legacy ? `/collection${hash}` : null);
    }
});

await test('local record history preserves the shelf while other navigation reaches Inertia', () => {
    const browser = new EventTarget();
    let collectionIsOpen = true;
    let recordIsOpen = false;
    browser.location = { pathname: '/collection', hash: '' };
    const events = [];
    globalThis.window = browser;
    globalThis.document = {
        querySelector: (selector) =>
            (selector === '.record-room' ? collectionIsOpen : recordIsOpen)
                ? {}
                : null,
    };

    try {
        initializeRecordHistory();
        browser.addEventListener('popstate', () => events.push('inertia'));
        browser.addEventListener('record-cabinet:navigate', () =>
            events.push('record'),
        );
        const navigate = (state) => {
            const event = new Event('popstate');
            Object.defineProperty(event, 'state', { value: state });
            browser.dispatchEvent(event);
        };

        navigate({ recordCabinet: 'record' });
        navigate({ recordCabinet: 'shelf' });
        assert.deepEqual(events, ['record', 'record']);

        recordIsOpen = true;
        navigate({ page: {} });
        recordIsOpen = false;
        browser.location.hash = '#record-123';
        navigate({ page: {} });
        browser.location.hash = '';
        assert.deepEqual(events, ['record', 'record', 'record', 'record']);

        for (const hash of [
            '#genre=Blues',
            '#artist=Pink+Floyd',
            '#decade=1990s',
            '#q=Live',
        ]) {
            browser.location.hash = hash;
            navigate({ page: {} });
            assert.equal(events.pop(), 'record');
        }
        browser.location.hash = '';
        browser.location.pathname = '/record/123/jeff-parker-happy-today';
        navigate({ recordCabinet: 'record' });
        assert.equal(events.at(-1), 'record');
        events.pop();
        browser.location.pathname = '/';
        navigate({ recordCabinet: 'shelf' });
        assert.equal(events.pop(), 'inertia');
        browser.location.pathname = '/collection';

        navigate({ page: { component: 'dashboard' } });
        navigate(null);
        collectionIsOpen = false;
        navigate({ recordCabinet: 'shelf' });
        assert.deepEqual(events, [
            'record',
            'record',
            'record',
            'record',
            'inertia',
            'inertia',
            'inertia',
        ]);
    } finally {
        delete globalThis.window;
        delete globalThis.document;
    }
});
