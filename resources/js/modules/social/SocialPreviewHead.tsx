import { Head } from '@inertiajs/react';

import type { SocialProps } from '@/modules/social/types';

export function SocialPreviewHead({
    preview,
}: {
    preview: SocialProps['socialPreview'];
}) {
    const properties = {
        'og:site_name': 'Plaat Club',
        'og:type': preview.type,
        'og:title': preview.title,
        'og:description': preview.description,
        'og:url': preview.url,
        'og:image': preview.image,
        'og:image:alt': preview.imageAlt,
        'og:image:width': '1200',
        'og:image:height': '630',
        'og:image:type': 'image/jpeg',
    };
    const names = {
        description: preview.description,
        'twitter:card': 'summary_large_image',
        'twitter:title': preview.title,
        'twitter:description': preview.description,
        'twitter:image': preview.image,
        'twitter:image:alt': preview.imageAlt,
    };

    return (
        <Head>
            {Object.entries(properties).map(([property, content]) => (
                <meta
                    key={property}
                    head-key={property}
                    property={property}
                    content={content}
                />
            ))}
            {Object.entries(names).map(([name, content]) => (
                <meta
                    key={name}
                    head-key={name}
                    name={name}
                    content={content}
                />
            ))}
            <link head-key="canonical" rel="canonical" href={preview.url} />
        </Head>
    );
}
