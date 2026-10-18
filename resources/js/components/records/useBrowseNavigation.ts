import {
    useEffect,
    useEffectEvent,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';

import {
    browseFromHash,
    browseHash,
    restoreBrowseState,
} from '@/components/records/browseState';
import type { BrowseState } from '@/components/records/browseState';
import { collection } from '@/routes';

export type BrowseUpdate =
    | Partial<BrowseState>
    | ((current: BrowseState) => BrowseState);

const initialBrowse = browseFromHash('');

/**
 * Stores the requested browse state. The active group is derived from it by
 * `collectionView`, so an unknown or missing group never ends up in state.
 */
export function useBrowseNavigation() {
    // The server cannot see the hash, so the first render matches the server and the hash is applied before paint.
    const [browse, setBrowse] = useState<BrowseState>(initialBrowse);
    const latest = useRef(browse);

    function apply(next: BrowseState) {
        latest.current = next;
        setBrowse(next);
    }

    useLayoutEffect(() => {
        const fromHash = browseFromHash(window.location.hash);
        if (browseHash(fromHash) !== browseHash(latest.current))
            apply(fromHash);
    }, []);

    const restore = useEffectEvent(() => {
        if (window.location.pathname !== collection.url()) return;
        const saved = window.history.state?.recordBrowse as
            | BrowseState
            | undefined;
        apply(
            restoreBrowseState(
                latest.current,
                browseFromHash(window.location.hash),
                saved,
            ),
        );
    });

    useEffect(() => {
        const onNavigate = () => restore();
        window.addEventListener('record-cabinet:navigate', onNavigate);
        window.addEventListener('hashchange', onNavigate);
        return () => {
            window.removeEventListener('record-cabinet:navigate', onNavigate);
            window.removeEventListener('hashchange', onNavigate);
        };
    }, []);

    function changeBrowse(update: BrowseUpdate) {
        const current = latest.current;
        const next =
            typeof update === 'function'
                ? update(current)
                : { ...current, ...update };
        const nextHash = browseHash(next);
        const currentState = {
            ...window.history.state,
            recordCabinet: 'shelf',
            recordBrowse: current,
        };
        window.history.replaceState(currentState, '', window.location.href);
        const state = { ...currentState, recordBrowse: next };
        const url = `${collection.url()}${nextHash}`;
        if (browseHash(current) === nextHash || current.query !== next.query) {
            window.history.replaceState(state, '', url);
        } else {
            window.history.pushState(state, '', url);
        }
        apply(next);
    }

    return { browse, changeBrowse };
}
