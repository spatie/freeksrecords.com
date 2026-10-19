import { createRoot } from 'react-dom/client';

import cabinetCss from '../css/record-cabinet.css?inline';
import controlsCss from '../css/record-controls.css?inline';
import '../css/profile-shelf.css';
import { CollectionBrowser } from '@/components/records/CollectionBrowser';
import type { CollectionRecordSummary } from '@/components/records/types';
import { useBrowseNavigation } from '@/components/records/useBrowseNavigation';

function ProfileShelf({ records }: { records: CollectionRecordSummary[] }) {
    const basePath = window.location.pathname + window.location.search;
    const { browse, changeBrowse } = useBrowseNavigation(basePath);

    return (
        <div className="record-room">
            <CollectionBrowser
                browse={browse}
                browseBasePath={basePath}
                onBrowse={changeBrowse}
                onSelect={(record) => {
                    const hash = window.location.hash.slice(1);
                    window.location.assign(
                        hash
                            ? `${record.shareUrl}?browse=${encodeURIComponent(hash)}`
                            : record.shareUrl,
                    );
                }}
                records={records}
                transitionRecordId={null}
            />
        </div>
    );
}

const mount = document.getElementById('profile-shelf');
const data = document.getElementById('profile-shelf-data');
if (mount && data) {
    // Reuse the original cabinet styles, but keep their selectors (including
    // legacy html transition rules) away from the surrounding Blade layout.
    const style = document.createElement('style');
    style.textContent = `@scope (.profile-record-room) { ${cabinetCss}\n${controlsCss} }`;
    document.head.append(style);
    createRoot(mount).render(
        <ProfileShelf records={JSON.parse(data.textContent ?? '[]')} />,
    );
}
