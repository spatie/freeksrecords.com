import { useEffect, useRef } from 'react';

import { BackToTop } from '@/components/records/BackToTop';
import { CollectionBrowser } from '@/components/records/CollectionBrowser';
import type { CollectionBrowserHandle } from '@/components/records/CollectionBrowser';
import { ListeningView } from '@/components/records/ListeningView';
import { RecordRoomHead } from '@/components/records/RecordRoomHead';
import type {
    RecordCollection,
    SharedRecord,
    SocialMeta,
} from '@/components/records/types';
import { useBrowseNavigation } from '@/components/records/useBrowseNavigation';
import { useRecordNavigation } from '@/components/records/useRecordNavigation';
import { home } from '@/routes';

export default function Welcome({
    collection,
    sharedRecord,
    social,
}: {
    collection: RecordCollection;
    sharedRecord: SharedRecord | null;
    social: SocialMeta;
}) {
    const browser = useRef<CollectionBrowserHandle>(null);
    const { browse, changeBrowse } = useBrowseNavigation();
    const navigation = useRecordNavigation(
        collection.records,
        sharedRecord?.instanceId ?? null,
    );
    const selected = navigation.selectedRecord;
    const hasSelectedRecord = selected !== null;

    useEffect(() => {
        if (!hasSelectedRecord) return;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [hasSelectedRecord]);

    return (
        <div
            className={`record-room ${hasSelectedRecord ? 'has-selected-record' : ''}`}
        >
            <RecordRoomHead
                record={selected}
                sharedRecord={sharedRecord}
                social={social}
            />
            <div
                className="record-room-content"
                inert={hasSelectedRecord}
                aria-hidden={hasSelectedRecord}
            >
                <a className="skip-records" href="#records">
                    Skip to records
                </a>
                <header className="collection-header">
                    <a className="collection-brand" href={home.url()}>
                        <span aria-hidden="true" className="brand-record" />{' '}
                        Freek’s records
                        <span className="brand-period">.</span>
                    </a>
                    <a href="/community">Join the listening circle ↗</a>
                </header>
                <main id="records">
                    <div className="collection-masthead">
                        <div className="listening-corner" aria-hidden="true">
                            <img
                                src="/images/listening-corner.webp"
                                alt=""
                                width={2048}
                                height={683}
                                fetchPriority="high"
                            />
                            <svg
                                className="room-cables"
                                viewBox="0 0 1400 120"
                                fill="none"
                            >
                                <path d="M 1160 4 C 1180 72 1360 18 1315 77 S 1180 105 1110 74 S 1010 107 1045 117" />
                                <path d="M 530 4 C 480 54 670 74 562 100 S 338 47 373 116" />
                            </svg>
                        </div>
                        <h1>
                            EVERYTHING IN
                            <br />
                            ITS RIGHT PLACE
                            <span className="masthead-dot">.</span>
                        </h1>
                    </div>
                    <CollectionBrowser
                        browse={browse}
                        ref={browser}
                        onBrowse={changeBrowse}
                        onSelect={navigation.openRecord}
                        records={collection.records}
                        transitionRecordId={navigation.transitionRecordId}
                    />
                </main>
                <BackToTop />
            </div>
            {selected && (
                <ListeningView
                    key={selected.instanceId}
                    onBack={navigation.closeRecord}
                    instant={navigation.isInitialRecord}
                    onArtist={(artist) => {
                        navigation.dismissRecord();
                        browser.current?.showArtist(artist);
                    }}
                    origin={navigation.origin}
                    record={selected}
                />
            )}
        </div>
    );
}
