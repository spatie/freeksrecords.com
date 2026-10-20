import { router, usePage } from '@inertiajs/react';

import cabinetCss from '../../../css/record-cabinet.css?inline';
import controlsCss from '../../../css/record-controls.css?inline';
import '../../../css/profile-shelf.css';
import {
    browseFromHash,
    browseHash,
    type BrowseState,
} from '@/components/records/browseState';
import { CollectionBrowser } from '@/components/records/CollectionBrowser';
import type { CollectionRecordSummary } from '@/components/records/types';
import type { BrowseUpdate } from '@/components/records/useBrowseNavigation';

export function ProfileShelf({
    records,
}: {
    records: CollectionRecordSummary[];
}) {
    const page = usePage<{ socialBrowse?: BrowseState }>();
    const basePath = page.url.split('#')[0];
    const hash =
        typeof window === 'undefined'
            ? (page.url.split('#')[1] ?? '')
            : window.location.hash;
    const browse =
        page.props.socialBrowse &&
        browseHash(page.props.socialBrowse) === browseHash(browseFromHash(hash))
            ? page.props.socialBrowse
            : browseFromHash(hash);
    function changeBrowse(update: BrowseUpdate) {
        const next =
            typeof update === 'function'
                ? update(browse)
                : { ...browse, ...update };
        const options = {
            url: `${basePath}${browseHash(next)}`,
            props: { ...page.props, socialBrowse: next },
            preserveState: true,
            preserveScroll: true,
        };
        if (
            browse.query !== next.query ||
            browseHash(browse) === browseHash(next)
        )
            router.replace(options);
        else router.push(options);
    }
    return (
        <div className="profile-record-room">
            <style>{`@scope (.profile-record-room) { ${cabinetCss}\n${controlsCss} }`}</style>
            <div className="record-room">
                <CollectionBrowser
                    browse={browse}
                    browseBasePath={basePath}
                    onBrowse={changeBrowse}
                    onSelect={(record) => {
                        const fragment = browseHash(browse).slice(1);
                        router.visit(
                            fragment
                                ? `${record.shareUrl}?browse=${encodeURIComponent(fragment)}`
                                : record.shareUrl,
                        );
                    }}
                    records={records}
                    transitionRecordId={null}
                />
            </div>
        </div>
    );
}
