import { useContext } from 'react';

import '../../../css/feed-shelf.css';
import { RecordShelf } from '@/components/records/RecordShelf';
import type { CollectionRecordSummary } from '@/components/records/types';
import { RecordOverlayContext } from '@/modules/social/RecordOverlay';
import { RecordRoom } from '@/modules/social/RecordRoom';

export function FeedShelf({ records }: { records: CollectionRecordSummary[] }) {
    const openRecord = useContext(RecordOverlayContext);
    return (
        <div className="feed-record-room">
            <RecordRoom>
                <RecordShelf
                    groupByYear={false}
                    onMore={() => {}}
                    onReset={() => {}}
                    onSelect={(record, source) =>
                        openRecord(record.shareUrl, source)
                    }
                    records={records}
                    transitionRecordId={null}
                    visibleCount={records.length}
                />
            </RecordRoom>
        </div>
    );
}
