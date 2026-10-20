import { Head, router } from '@inertiajs/react';
import { useEffect } from 'react';

import { ListeningView } from '@/components/records/ListeningView';
import { browseHash } from '@/components/records/browseState';
import { RecordRoom } from '@/modules/social/RecordRoom';
import { RecordSocial } from '@/modules/social/RecordSocial';
import type { RecordProps } from '@/modules/social/types';

export function RecordPage(props: RecordProps) {
    const { record, collectionUrl } = props;
    const payload = record.payload;

    useEffect(() => {
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previous;
        };
    }, []);

    return (
        <>
            <Head title={payload.displayTitle} />
            <RecordRoom>
                <ListeningView
                    instant
                    origin={null}
                    record={{
                        ...payload,
                        trackTitles: payload.tracks.map((track) => track.title),
                        shareUrl: `/shelves/${record.id}`,
                    }}
                    details={payload}
                    onBack={() => router.visit(collectionUrl)}
                    onArtist={(artist) =>
                        router.visit(
                            `${collectionUrl.split('#')[0]}${browseHash({ mode: 'artist', group: artist, query: '', visibleCount: 25 })}`,
                        )
                    }
                >
                    <div className="record-social">
                        <RecordSocial {...props} />
                    </div>
                </ListeningView>
            </RecordRoom>
        </>
    );
}
