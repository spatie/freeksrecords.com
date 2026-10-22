import type { CollectionRecordSummary } from '../../components/records/types';

const additionDate = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Brussels',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

export function groupFeedRecords(records: CollectionRecordSummary[]) {
    const groups: {
        date: string;
        owner: CollectionRecordSummary['owner'];
        records: CollectionRecordSummary[];
    }[] = [];

    for (const record of records) {
        const date = additionDate.format(new Date(record.addedAt));
        const previous = groups.at(-1);

        if (
            previous?.owner?.profileUrl === record.owner?.profileUrl &&
            previous?.date === date
        ) {
            previous.records.push(record);
        } else {
            groups.push({ date, owner: record.owner, records: [record] });
        }
    }

    return groups;
}
