import { router, usePage } from '@inertiajs/react';
import { useContext } from 'react';
import {
    browseFromHash,
    browseHash,
    type BrowseState,
} from '@/components/records/browseState';
import { CollectionBrowser } from '@/components/records/CollectionBrowser';
import type { CollectionRecordSummary } from '@/components/records/types';
import type { BrowseUpdate } from '@/components/records/useBrowseNavigation';
import { RecordRoom } from '@/modules/social/RecordRoom';
import { RecordOverlayContext } from '@/modules/social/RecordOverlay';
import type { SocialProps } from '@/modules/social/types';

export function ProfileShelf({
    records,
}: {
    records: CollectionRecordSummary[];
}) {
    const page = usePage<SocialProps & { socialBrowse?: BrowseState }>();
    const openRecord = useContext(RecordOverlayContext);
    const basePath = page.url.split(/[?#]/)[0];
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
        <RecordRoom>
            <CollectionBrowser
                browse={browse}
                browseBasePath={basePath}
                onBrowse={changeBrowse}
                onSelect={(record) => {
                    openRecord(
                        record.shareUrl,
                        document.querySelector<HTMLElement>(
                            `[data-record-id="${record.instanceId}"] .catalog-record-open`,
                        ),
                    );
                }}
                records={records}
                transitionRecordId={
                    page.props.recordOverlay?.record.payload.instanceId ?? null
                }
            />
        </RecordRoom>
    );
}
