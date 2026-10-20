import { useEffect, useState } from 'react';

import RecordDetailsController from '@/actions/App/Http/Controllers/RecordDetailsController';
import type { CollectionRecord } from '@/components/records/types';

const cachedDetails = new Map<number, CollectionRecord>();

type DetailsState = {
    instanceId: number;
    details: CollectionRecord | null;
    error: boolean;
    attempt: number;
};

/**
 * Loads the tracklist for a record. State is tagged with the record it belongs
 * to, so switching records never shows the previous record's details.
 */
export function useRecordDetails(
    instanceId: number,
    supplied?: CollectionRecord,
) {
    const [state, setState] = useState<DetailsState>(() => ({
        instanceId,
        details: null,
        error: false,
        attempt: 0,
    }));
    const current: DetailsState =
        state.instanceId === instanceId
            ? state
            : { instanceId, details: null, error: false, attempt: 0 };
    const details =
        supplied ?? cachedDetails.get(instanceId) ?? current.details;

    useEffect(() => {
        if (supplied) return;
        if (cachedDetails.has(instanceId)) return;
        const controller = new AbortController();
        void fetch(RecordDetailsController.url(instanceId), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) throw new Error('Unable to load record');
                const result = (await response.json()) as CollectionRecord;
                if (controller.signal.aborted) return;
                if (result.tracks.length) cachedDetails.set(instanceId, result);
                setState({
                    instanceId,
                    details: result,
                    error: false,
                    attempt: 0,
                });
            })
            .catch(() => {
                if (controller.signal.aborted) return;
                setState((previous) => ({
                    instanceId,
                    details: null,
                    error: true,
                    attempt:
                        previous.instanceId === instanceId
                            ? previous.attempt
                            : 0,
                }));
            });
        return () => controller.abort();
    }, [instanceId, current.attempt, supplied]);

    function retry() {
        setState({
            instanceId,
            details: null,
            error: false,
            attempt: current.attempt + 1,
        });
    }

    return { details, error: details ? false : current.error, retry };
}
