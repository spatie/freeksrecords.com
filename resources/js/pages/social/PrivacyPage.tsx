import { Head } from '@inertiajs/react';

export function PrivacyPage() {
    return (
        <>
            <Head title="Privacy draft" />
            <article className="legal-document">
                <p>
                    <strong>Draft for review — not yet in effect.</strong>
                </p>
                <h1>Privacy</h1>
                <p>
                    This is an owner-review draft for Plaat Club, not an
                    approved privacy policy.
                </p>
                <p className="review-note">
                    <strong>Before publishing:</strong> the owner must confirm
                    legal operator details, retention and backups, and the legal
                    basis for processing. Infrastructure, tracking and data
                    locations also need verification.
                </p>
                <h2>Accounts and Google sign-in</h2>
                <p>
                    Plaat Club stores your email, display name, public handle
                    and a password hash, not your plain-text password. Optional
                    Google sign-in uses basic identity: your verified email,
                    name and stable Google identifier (sub). Google access and
                    refresh tokens are not retained; it does not request Gmail
                    or Drive access.
                </p>
                <p>
                    Existing password users must sign in and explicitly link
                    Google in Settings using their current password. Accounts
                    are never automatically linked just because email addresses
                    match.
                </p>
                <p>
                    <strong>
                        Proposed commitment, subject to owner approval:
                    </strong>{' '}
                    Google data would be used only for your account and sign-in,
                    not for advertising or sale.
                </p>
                <h2>Discogs connection</h2>
                <p>
                    Connecting through Discogs OAuth stores your Discogs
                    username and encrypted credentials to import collection
                    metadata and artwork. The scheduled daily sync is a one-way
                    mirror from Discogs, including removals; it does not write
                    collection changes back to Discogs. Syncs can fail or be
                    delayed.
                </p>
                <p>
                    Disconnecting removes stored Discogs credentials but keeps
                    imported records. You can also revoke the app’s grant at
                    Discogs.
                </p>
                <h2>What other people can see</h2>
                <p>
                    New collections start private. Your display name and handle
                    remain public. Collection visibility choices are:
                </p>
                <ul>
                    <li>
                        Only me: only you can view your collection through the
                        app.
                    </li>
                    <li>
                        Friends: you and accepted, mutual friends can view it.
                    </li>
                    <li>Everyone: anyone, including guests, can view it.</li>
                </ul>
                <p>
                    Shared record notes follow collection visibility. Private
                    notes are shown only to the owner in the app; this is not a
                    claim that the service operator cannot access stored data.
                </p>
                <p>
                    The app stores friendships, comments, appreciations and
                    in-app notifications to support social features. A comment’s
                    author or the collection owner can delete it. Disabling
                    comments prevents new comments, but does not hide existing
                    ones.
                </p>
                <h2>Email and shared copies</h2>
                <p>
                    The optional weekly friends digest is enabled by default and
                    can be disabled in Settings. It checks current collection
                    visibility before sending and sends no empty-week email.
                    Account emails support verification and password recovery.
                    Already delivered email cannot be recalled, and viewers may
                    copy information you share.
                </p>
                <h2>Running the service</h2>
                <p>
                    The infrastructure described for owner review is Laravel
                    Cloud for hosting, Cloudflare for DNS and security, and
                    Resend for transactional mail. These providers process data
                    needed for their services. Sessions and diagnostics may
                    process IP addresses, user-agent details and technical
                    activity to support sign-in, operation and troubleshooting.
                    Tracking configuration and data residency have not been
                    verified by this draft.
                </p>
                <h2>Your requests</h2>
                <p>
                    Email <a href="mailto:freek@spatie.be">freek@spatie.be</a>{' '}
                    for data access, correction or deletion requests, or privacy
                    questions. There is no self-service account deletion.
                    Retention, deletion handling and backup treatment need owner
                    confirmation before publication.
                </p>
            </article>
        </>
    );
}
