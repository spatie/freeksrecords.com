import { Head } from '@inertiajs/react';

import RecordShareImageController from '@/actions/App/Http/Controllers/RecordShareImageController';

import type {
    CollectionRecordSummary,
    SharedRecord,
    SocialMeta,
} from '@/components/records/types';

type RecordRoomHeadProps = {
    record: CollectionRecordSummary | null;
    sharedRecord: SharedRecord | null;
    social: SocialMeta;
};

/** Bump together with the `v` query the server adds to shared record images. */
const recordShareImageVersion = 4;

function shareImageUrl(
    record: CollectionRecordSummary | null,
    sharedRecord: SharedRecord | null,
    social: SocialMeta,
): string {
    if (!record) {
        return social.image;
    }

    if (record.instanceId === sharedRecord?.instanceId) {
        return sharedRecord.image;
    }

    return new URL(
        RecordShareImageController.url(record.instanceId, {
            query: { v: recordShareImageVersion },
        }),
        social.url,
    ).href;
}

/**
 * Title, social cards and structured data for the collection or the open
 * record. With SSR this is the only source of these tags; app.blade.php only
 * renders them as a fallback when the SSR server is unavailable.
 */
export function RecordRoomHead({
    record,
    sharedRecord,
    social,
}: RecordRoomHeadProps) {
    const title = record
        ? `${record.displayTitle} by ${record.artist}`
        : social.title;
    const description = record?.shareDescription ?? social.description;
    const url = record ? new URL(record.shareUrl, social.url).href : social.url;
    const image = shareImageUrl(record, sharedRecord, social);
    const imageAlt = record
        ? `${record.displayTitle} sleeve and vinyl, by ${record.artist}`
        : social.imageAlt;
    const structuredData = {
        '@context': 'https://schema.org',
        '@type': record ? 'MusicAlbum' : 'CollectionPage',
        name: record?.displayTitle ?? title,
        description,
        url,
        image,
        inLanguage: 'en',
        ...(record
            ? { byArtist: { '@type': 'MusicGroup', name: record.artist } }
            : {
                  isPartOf: { '@type': 'WebSite', name: title, url },
                  author: { '@type': 'Person', name: 'Freek Van der Herten' },
              }),
    };

    return (
        <Head>
            <title>{title}</title>
            <script head-key="structured-data" type="application/ld+json">
                {JSON.stringify(structuredData).replace(/</g, '\\u003c')}
            </script>
            <meta
                head-key="description"
                name="description"
                content={description}
            />
            <meta
                head-key="og:type"
                property="og:type"
                content={record ? 'music.album' : 'website'}
            />
            <meta
                head-key="og:site_name"
                property="og:site_name"
                content={social.title}
            />
            <meta head-key="og:title" property="og:title" content={title} />
            <meta
                head-key="og:description"
                property="og:description"
                content={description}
            />
            <meta head-key="og:url" property="og:url" content={url} />
            <meta head-key="og:image" property="og:image" content={image} />
            <meta
                head-key="og:image:width"
                property="og:image:width"
                content="1200"
            />
            <meta
                head-key="og:image:height"
                property="og:image:height"
                content="630"
            />
            <meta
                head-key="og:image:type"
                property="og:image:type"
                content="image/jpeg"
            />
            <meta
                head-key="og:image:alt"
                property="og:image:alt"
                content={imageAlt}
            />
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content="summary_large_image"
            />
            <meta
                head-key="twitter:title"
                name="twitter:title"
                content={title}
            />
            <meta
                head-key="twitter:description"
                name="twitter:description"
                content={description}
            />
            <meta
                head-key="twitter:image"
                name="twitter:image"
                content={image}
            />
            <meta
                head-key="twitter:image:alt"
                name="twitter:image:alt"
                content={imageAlt}
            />
            <link head-key="canonical" rel="canonical" href={url} />
        </Head>
    );
}
