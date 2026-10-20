import { router } from '@inertiajs/react';

import cabinetCss from '../../../css/record-cabinet.css?inline';
import feedCss from '../../../css/feed-shelf.css?inline';
import '../../../css/profile-shelf.css';
import { RecordShelf } from '@/components/records/RecordShelf';
import type { CollectionRecordSummary } from '@/components/records/types';

export function FeedShelf({ records }: { records: CollectionRecordSummary[] }) {
    return (
        <div className="feed-record-room profile-record-room">
            <style>{`@scope (.feed-record-room) { ${cabinetCss}\n${feedCss} }`}</style>
            <div className="record-room">
                <RecordShelf
                    groupByYear={false}
                    onMore={() => {}}
                    onReset={() => {}}
                    onSelect={(record) => router.visit(record.shareUrl)}
                    records={records}
                    transitionRecordId={null}
                    visibleCount={records.length}
                />
            </div>
        </div>
    );
}
