import type { CollectionRecordSummary } from '@/components/records/types';
import type { Pagination, Person, Visibility } from '@/modules/social/types';

export type ListSummary = {
    id: number;
    publicId: string;
    name: string;
    description: string | null;
    visibility: Visibility;
    count: number;
};
export type ListPreview = ListSummary & { covers: string[]; owner: Person };
export type RecordList = Omit<ListSummary, 'count'> & {
    url: string;
    owner: Person;
    canEdit: boolean;
    removedCount: number;
};
export type ListRecord = CollectionRecordSummary & {
    copyId: number;
    copyPublicId: string;
};
export type PickerRecord = Pick<
    ListRecord,
    | 'copyId'
    | 'copyPublicId'
    | 'artist'
    | 'displayTitle'
    | 'year'
    | 'originalYear'
    | 'format'
    | 'cover'
>;
export type ListProps = { list: RecordList; records: ListRecord[] };
export type ListEditProps = ListProps & {
    query: string;
    results: Pagination<PickerRecord>;
};
