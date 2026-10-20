import { useContext } from 'react';

import cabinetCss from '../../../css/record-cabinet.css?inline';
import feedCss from '../../../css/feed-shelf.css?inline';
import '../../../css/profile-shelf.css';
import { RecordShelf } from '@/components/records/RecordShelf';
import type { CollectionRecordSummary } from '@/components/records/types';
import { RecordOverlayContext } from '@/modules/social/RecordOverlay';

export function FeedShelf({ records }: { records: CollectionRecordSummary[] }) {
    const openRecord = useContext(RecordOverlayContext);
    return (
        <div className="feed-record-room profile-record-room">
            <style>{`@scope (.feed-record-room) { ${cabinetCss}\n${feedCss} }`}</style>
            <div className="record-room">
                <RecordShelf
                    groupByYear={false}
                    onMore={() => {}}
                    onReset={() => {}}
                    onSelect={(record) =>
                        openRecord(
                            record.shareUrl,
                            document.activeElement instanceof HTMLElement
                                ? document.activeElement
                                : null,
                        )
                    }
                    records={records}
                    transitionRecordId={null}
                    visibleCount={records.length}
                />
            </div>
        </div>
    );
}
