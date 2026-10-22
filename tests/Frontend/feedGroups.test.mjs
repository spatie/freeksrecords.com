import assert from 'node:assert/strict';
import test from 'node:test';

import { groupFeedRecords } from '../../resources/js/modules/social/feedGroups.ts';

await test('feed shelves group only consecutive additions by friend and Brussels calendar date', () => {
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
            date: group.date,
            records: group.records.map((record) => record.shareUrl),
        })),
        [
            {
                friend: '/first',
                date: '2026-10-22',
                records: ['/record/1', '/record/2'],
            },
            { friend: '/second', date: '2026-10-22', records: ['/record/3'] },
            { friend: '/first', date: '2026-10-22', records: ['/record/4'] },
            { friend: '/first', date: '2026-10-21', records: ['/record/5'] },
        ],
    );
    assert.deepEqual(
        groups.flatMap((group) => group.records),
        records,
    );
    assert.deepEqual(groupFeedRecords([]), []);
});
