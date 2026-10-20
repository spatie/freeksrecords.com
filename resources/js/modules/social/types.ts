import type {
    CollectionRecord,
    CollectionRecordSummary,
} from '@/components/records/types';

export type Visibility = 'only_me' | 'friends' | 'everyone';

export type Person = {
    id: number;
    name: string;
    handle: string;
    profileUrl: string;
};

export type Viewer = Person & {
    email: string;
    verified: boolean;
    visibility: Visibility;
    legacyOwner: boolean;
    commentsEnabled: boolean;
    digestEnabled: boolean;
    googleConnected: boolean;
    discogsConnected: boolean;
    discogsUsername: string | null;
    importedAt: string | null;
    lastSyncedAt: string | null;
    syncStatus: string | null;
    collectionCount: number;
};

export type SocialProps = {
    [key: string]: unknown;
    auth: { user: Viewer | null };
    csrfToken: string;
    googleEnabled: boolean;
    notificationCount: number;
    demoUsers: { email: string; label: string; url: string }[];
    flash: { status: string | null };
    errors: Record<string, string>;
};

export type Pagination<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type Collector = Person & {
    recordCount: number;
    records: RecordArtwork[];
};

export type RecordArtwork = { cover: string; title: string; url: string };
export type HomeProps = { collectors: Collector[]; artwork: RecordArtwork[] };
export type CollectionsProps = { collectors: Pagination<Collector> };
export type AuthProps = {
    artwork: RecordArtwork[];
    mode: 'login' | 'register' | 'forgot' | 'reset' | 'verify';
    token?: string;
    email?: string;
};
export type ProfileProps = {
    person: Person;
    visible: boolean;
    records: CollectionRecordSummary[] | null;
};
export type FeedProps = {
    records: Pagination<CollectionRecordSummary>;
    collectionCount: number;
    incomingRequests: Person[];
};
export type FriendsProps = {
    query: string;
    people: Pagination<Person>;
    connections: {
        user_low: number;
        user_high: number;
        requested_by: number;
        accepted_at: string | null;
    }[];
};
export type RecordProps = {
    collectionUrl: string;
    record: {
        id: number;
        owner: Person;
        payload: CollectionRecord;
        formats: string[];
        addedLabel: string;
        sharedNote: string | null;
        privateNote?: string | null;
        canEditNotes: boolean;
        commentsEnabled: boolean;
    };
    comments: Pagination<{
        id: number;
        body: string;
        user: Person;
        canDelete: boolean;
    }>;
    appreciations: number;
    appreciated: boolean;
};

export type Notifications = {
    notifications: Pagination<{
        id: number;
        actor: Person;
        text: string;
        record: { title: string; artist: string; cover: string } | null;
        url: string;
        unread: boolean;
    }>;
    unreadCount: number;
};
