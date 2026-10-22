import { Head, Link, usePage } from '@inertiajs/react';

import type { SocialProps } from '@/modules/social/types';

export function WeeklyEmailPage({ emailHtml }: { emailHtml: string }) {
    const user = usePage<SocialProps>().props.auth.user;

    return (
        <>
            <Head title="A little good company in your inbox" />
            <div className="digest-preview">
                <div className="digest-preview-copy">
                    <p className="page-eyebrow">The weekly email · A preview</p>
                    <h1>
                        Good finds.
                        <br />
                        Good company.
                    </h1>
                    <p className="digest-preview-intro">
                        A small catch-up with your friends’ newest records. No
                        endless feed. Just something good to listen to.
                    </p>
                    <dl>
                        <div>
                            <dt>Once a week.</dt>
                            <dd>
                                New additions, newest first. An initial
                                collection import doesn’t fill your inbox.
                            </dd>
                        </div>
                        <div>
                            <dt>A quiet week? No email.</dt>
                            <dd>
                                If your friends haven’t added anything to share
                                with you, we send nothing.
                            </dd>
                        </div>
                        <div>
                            <dt>Private stays private.</dt>
                            <dd>
                                Friendships and collection visibility are
                                checked when the email is sent. Only records you
                                can see then are included.
                            </dd>
                        </div>
                    </dl>
                    <Link
                        className="button"
                        href={user?.verified ? '/settings' : '/about'}
                    >
                        {user?.verified
                            ? 'Back to preferences'
                            : 'About Plaat Club'}
                    </Link>
                </div>
                <figure className="digest-preview-mail">
                    <figcaption>
                        In your inbox{' '}
                        <span>Example · fictional people & records</span>
                    </figcaption>
                    <iframe
                        title="Example weekly email: three new records from Alex and Robin"
                        srcDoc={emailHtml}
                        sandbox=""
                    />
                </figure>
            </div>
        </>
    );
}
