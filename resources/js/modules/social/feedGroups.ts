import type { CollectionRecordSummary } from '../../components/records/types';

export function groupFeedRecords(records: CollectionRecordSummary[]) {
    const groups: {
        owner: CollectionRecordSummary['owner'];
        records: CollectionRecordSummary[];
    }[] = [];

    for (const record of records) {
        const group = groups.find(
            (group) => group.owner?.profileUrl === record.owner?.profileUrl,
        );

        if (group) {
            group.records.push(record);
        } else {
            groups.push({ owner: record.owner, records: [record] });
        }
    }

    return groups;
}
