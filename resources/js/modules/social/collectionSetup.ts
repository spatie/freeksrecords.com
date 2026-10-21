import type { Viewer } from '@/modules/social/types';

export function isSettingUpCollection(user: Viewer): boolean {
    return !user.importedAt && (!user.collectionCount || user.discogsConnected);
}
