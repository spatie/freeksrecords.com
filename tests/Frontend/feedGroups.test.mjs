import assert from 'node:assert/strict';
import test from 'node:test';

import { groupFeedRecords } from '../../resources/js/modules/social/feedGroups.ts';

await test('feed shelves collect each friend’s finds across dates, ordered by their latest find', () => {
    const first = { name: 'Same name', profileUrl: '/first', avatarUrl: null };
    const second = {
        name: 'Same name',
        profileUrl: '/second',
        avatarUrl: null,
    };
    const records = [
        {
            owner: first,
            addedAt: '2026-10-22T00:30:00Z',
            shareUrl: '/record/1',
        },
        {
            owner: first,
            addedAt: '2026-10-21T22:30:00Z',
            shareUrl: '/record/2',
        },
        {
            owner: second,
            addedAt: '2026-10-21T22:15:00Z',
            shareUrl: '/record/3',
        },
        {
            owner: first,
            addedAt: '2026-10-21T22:00:00Z',
            shareUrl: '/record/4',
        },
        {
            owner: first,
            addedAt: '2026-10-21T21:59:59Z',
            shareUrl: '/record/5',
        },
    ];
    const groups = groupFeedRecords(records);

    assert.deepEqual(
        groups.map((group) => ({
            friend: group.owner.profileUrl,
            records: group.records.map((record) => record.shareUrl),
        })),
        [
            {
                friend: '/first',
                records: ['/record/1', '/record/2', '/record/4', '/record/5'],
            },
            { friend: '/second', records: ['/record/3'] },
        ],
    );
    assert.deepEqual(
        records.map((record) => record.shareUrl),
        ['/record/1', '/record/2', '/record/3', '/record/4', '/record/5'],
    );
    assert.deepEqual(groupFeedRecords([]), []);
});
