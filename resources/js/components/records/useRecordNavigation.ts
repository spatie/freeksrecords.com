import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { flushSync } from 'react-dom';

import { sleeveOrigin } from '@/components/records/recordMotion';
import type { SleeveOrigin } from '@/components/records/recordMotion';

import type { CollectionRecordSummary } from '@/components/records/types';
import { collection } from '@/routes';

function recordFromLocation(): number | null {
    const match = window.location.hash.match(/^#record-(\d+)$/);
    if (match) return Number(match[1]);
    const shared = window.location.pathname.match(
        /^\/record\/(\d+)(?:\/[^/]+)?$/,
    );
    return shared ? Number(shared[1]) : null;
}

/**
 * @param initialRecordId The record the server rendered, so a shared link shows the record without waiting for hydration.
 */
export function useRecordNavigation(
    records: CollectionRecordSummary[],
    initialRecordId: number | null = null,
) {
    const [selectedId, setSelectedId] = useState<number | null>(
        initialRecordId,
    );
    const [isInitialRecord, setIsInitialRecord] = useState(
        initialRecordId !== null,
    );
    const [transitionRecordId, setTransitionRecordId] = useState<number | null>(
        null,
    );
    const [origin, setOrigin] = useState<SleeveOrigin | null>(null);
    const returnScroll = useRef(0);
    const returnRecord = useRef<number | null>(null);
    const selectedRecord =
        records.find((record) => record.instanceId === selectedId) ?? null;

    function navigate(nextId: number | null) {
        const destination = records.some(
            (record) => record.instanceId === nextId,
        )
            ? nextId
            : null;
        if (destination === selectedId) return;
        const source = destination === null ? null : sleeveOrigin(destination);
        flushSync(() => {
            setIsInitialRecord(false);
            setOrigin(source);
            setTransitionRecordId(destination);
            setSelectedId(destination);
        });
        if (destination === null) {
            window.scrollTo({ top: returnScroll.current, behavior: 'instant' });
            document
                .querySelector<HTMLButtonElement>(
                    `[data-record-id="${returnRecord.current}"] .catalog-record-open`,
                )
                ?.focus({ preventScroll: true });
        }
    }

    function openRecord(record: CollectionRecordSummary) {
        if (selectedId === null) {
            returnScroll.current = window.scrollY;
            returnRecord.current = record.instanceId;
            window.history.replaceState(
                { ...window.history.state, recordCabinet: 'shelf' },
                '',
                window.location.href,
            );
            window.history.pushState(
                { ...window.history.state, recordCabinet: 'record' },
                '',
                record.shareUrl,
            );
        } else {
            window.history.replaceState(
                window.history.state,
                '',
                record.shareUrl,
            );
        }
        navigate(record.instanceId);
    }

    function closeRecord() {
        if (returnRecord.current !== null) {
            window.history.back();
            return;
        }
        window.history.replaceState(
            { ...window.history.state, recordCabinet: 'shelf' },
            '',
            collection.url(),
        );
        navigate(null);
    }

    function dismissRecord() {
        window.history.replaceState(
            { ...window.history.state, recordCabinet: 'shelf' },
            '',
            collection.url(),
        );
        setIsInitialRecord(false);
        setSelectedId(null);
        setTransitionRecordId(null);
        setOrigin(null);
    }

    const onHistoryChange = useEffectEvent(() =>
        navigate(recordFromLocation()),
    );
    const needsInitialNavigation = useEffectEvent(
        () => recordFromLocation() !== selectedId,
    );
    useEffect(() => {
        const onPopState = () => onHistoryChange();
        window.addEventListener('record-cabinet:navigate', onPopState);
        let mounted = true;
        // Legacy #record-123 links are only visible in the browser. flushSync cannot run inside an effect, hence the microtask.
        if (needsInitialNavigation())
            queueMicrotask(() => {
                if (mounted) onHistoryChange();
            });
        return () => {
            mounted = false;
            window.removeEventListener('record-cabinet:navigate', onPopState);
        };
    }, []);

    return {
        selectedRecord,
        isInitialRecord,
        transitionRecordId,
        origin,
        openRecord,
        closeRecord,
        dismissRecord,
    };
}
