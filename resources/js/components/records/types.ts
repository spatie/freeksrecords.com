export type CollectionTrack = {
    position: string;
    title: string;
    artist: string;
    duration: string;
    appleMusicUrl: string;
    appleMusicSearch: boolean;
    spotifyUrl: string;
    spotifySearch: boolean;
};

export type CollectionRecord = {
    id: number;
    instanceId: number;
    artist: string;
    artists: string[];
    title: string;
    displayTitle: string;
    edition: string;
    year: number;
    originalYear: number | null;
    originalReleaseDate?: string | null;
    pressingReleaseDate?: string | null;
    addedAt: string;
    label: string;
    catalogNumber: string;
    format: string;
    mediaFormats?: string[];
    genres: string[];
    styles: string[];
    cover: string;
    coverPreview?: string;
    discogsUrl: string;
    appleMusicUrl: string;
    appleMusicSearch: boolean;
    spotifyUrl: string;
    spotifySearch: boolean;
    trackSource: 'release' | 'master';
    tracks: CollectionTrack[];
};

export type RecordCollection = {
    records: CollectionRecordSummary[];
};

export type SharedRecord = {
    instanceId: number;
    title: string;
    artist: string;
    description: string;
    url: string;
    image: string;
};

export type SocialMeta = {
    title: string;
    description: string;
    url: string;
    image: string;
    imageAlt: string;
};

export type CollectionMode = 'recent' | 'decade' | 'genre' | 'artist';
export type CollectionGroup = { name: string; count: number };

export type CollectionRecordSummary = Omit<
    CollectionRecord,
    | 'tracks'
    | 'catalogNumber'
    | 'discogsUrl'
    | 'edition'
    | 'pressingReleaseDate'
    | 'trackSource'
> & {
    trackTitles: string[];
    shareUrl: string;
    shareDescription?: string;
    owner?: { name: string; profileUrl: string; avatarUrl: string | null };
};
