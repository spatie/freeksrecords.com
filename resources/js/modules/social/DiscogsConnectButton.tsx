import { usePage } from '@inertiajs/react';

import type { SocialProps } from '@/modules/social/types';

export function DiscogsConnectButton({
    reconnect = false,
}: {
    reconnect?: boolean;
}) {
    const { csrfToken } = usePage<SocialProps>().props;

    return (
        <form method="post" action="/discogs/connect">
            <input type="hidden" name="_token" value={csrfToken} />
            <button className="button">
                {reconnect ? 'Reconnect Discogs' : 'Connect Discogs'}
            </button>
        </form>
    );
}
