import { Head, router } from '@inertiajs/react';

import { browseHash } from '@/components/records/browseState';
import { ListeningView } from '@/components/records/ListeningView';
import type { SleeveOrigin } from '@/components/records/recordMotion';
import { RecordRoom } from '@/modules/social/RecordRoom';
import { RecordSocial } from '@/modules/social/RecordSocial';
import type { RecordProps } from '@/modules/social/types';

export function SocialRecordView({
    data,
    onBack,
    origin = null,
    returnOrigin,
}: {
    data: RecordProps;
    onBack: () => void;
    origin?: SleeveOrigin | null;
    returnOrigin?: () => SleeveOrigin | null;
}) {
    const { record, collectionUrl } = data;
    const payload = record.payload;

    return (
        <RecordRoom>
            <Head title={payload.displayTitle} />
            <ListeningView
                clubLayout
                instant={origin === null}
                origin={origin}
                returnOrigin={returnOrigin}
                record={{
                    ...payload,
                    trackTitles: payload.tracks.map((track) => track.title),
                    shareUrl: `/shelves/${record.id}`,
                }}
                details={payload}
                onBack={onBack}
                onArtist={(artist) =>
                    router.visit(
                        `${collectionUrl.split('#')[0]}${browseHash({ mode: 'artist', group: artist, query: '', visibleCount: 25 })}`,
                    )
                }
            >
                <div className="record-social">
                    <RecordSocial {...data} />
                </div>
            </ListeningView>
        </RecordRoom>
    );
}
