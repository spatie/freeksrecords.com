import { createRoot } from 'react-dom/client';

import cabinetCss from '../css/record-cabinet.css?inline';
import '../css/profile-shelf.css';
import feedCss from '../css/feed-shelf.css?inline';
import { RecordShelf } from '@/components/records/RecordShelf';
import type { CollectionRecordSummary } from '@/components/records/types';

const mount = document.getElementById('feed-shelf');
const data = document.getElementById('feed-shelf-data');

if (mount && data) {
    const records: CollectionRecordSummary[] = JSON.parse(
        data.textContent ?? '[]',
    );
    const style = document.createElement('style');
    style.textContent = `@scope (.feed-record-room) { ${cabinetCss}\n${feedCss} }`;
    document.head.append(style);
    createRoot(mount).render(
        <div className="record-room">
            <RecordShelf
                groupByYear={false}
                onMore={() => {}}
                onReset={() => {}}
                records={records}
                transitionRecordId={null}
                visibleCount={records.length}
            />
        </div>,
    );
}
