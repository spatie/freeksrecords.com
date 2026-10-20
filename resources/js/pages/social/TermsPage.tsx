import { Head } from '@inertiajs/react';

export function TermsPage() {
    return (
        <>
            <Head title="Terms draft" />
            <article className="legal-document">
                <p>
                    <strong>Draft for review — not yet in effect.</strong>
                </p>
                <h1>Terms</h1>
                <p>
                    These proposed terms are for owner approval, not approved or
                    effective terms.
                </p>
                <p className="review-note">
                    <strong>Before publishing:</strong> the owner must confirm
                    legal operator details, retention and backups, and the legal
                    basis for processing described in the privacy draft.
                </p>
                <h2>What Plaat Club is for</h2>
                <p>
                    Plaat Club is a Discogs companion for displaying collections
                    and sharing them with others, not a replacement for Discogs.
                    It is not affiliated with Discogs. It is not a marketplace
                    and does not arrange purchases, sales or other transactions.
                </p>
                <h2>Your account and access</h2>
                <p>
                    Keep your sign-in details secure and use only accounts and
                    connections you are authorised to use. Do not bypass access
                    controls, attempt to view private collections without
                    permission, or disrupt the service. Contact us if you
                    suspect unauthorised access.
                </p>
                <h2>Respect people and their work</h2>
                <ul>
                    <li>
                        Do not harass others, send spam or impersonate someone.
                    </li>
                    <li>
                        Respect copyright and other people’s privacy. Share only
                        material you have permission to share.
                    </li>
                    <li>
                        Do not misuse comments, appreciations or other features
                        to target people.
                    </li>
                </ul>
                <h2>Your content and sharing</h2>
                <p>
                    You retain ownership of content you contribute. The proposed
                    permission is limited to hosting and displaying it to
                    operate Plaat Club according to your selected visibility,
                    including relevant notifications and digests. Private notes
                    remain owner-only in the app. Viewers may copy shared
                    information, and delivered email cannot be recalled.
                </p>
                <p>
                    Artwork and imported metadata may belong to others. Using
                    Plaat Club does not grant rights to commercially reuse that
                    material or other users’ content.
                </p>
                <h2>Availability and problems</h2>
                <p>
                    Availability and syncing can fail or be delayed. Check
                    Discogs for your source collection; the one-way mirror
                    includes removals. Do not rely on Plaat Club as your only
                    collection record.
                </p>
                <p>
                    Report problems, content concerns or serious misuse to{' '}
                    <a href="mailto:freek@spatie.be">freek@spatie.be</a>.
                    Proposed handling of serious misuse is reasonable,
                    proportionate review, which may include restricting access
                    or removing offending content.
                </p>
            </article>
        </>
    );
}
