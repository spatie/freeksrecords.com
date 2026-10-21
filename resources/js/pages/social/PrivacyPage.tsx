import { Head, Link } from '@inertiajs/react';

export function PrivacyPage() {
    return (
        <>
            <Head title="Privacy" />
            <article className="legal-document">
                <Link className="text-button" href="/about">
                    ← About Plaat Club
                </Link>
                <h1>Privacy</h1>
                <p className="muted">Last updated: 21 October 2026</p>
                <h2>Who we are</h2>
                <p>
                    Plaat Club is operated by Spatie bv, Kruikstraat 22 bus 12,
                    2018 Antwerp, Belgium, company number 0809.387.596. Spatie
                    bv is responsible for the personal data described in this
                    policy. Contact{' '}
                    <a href="mailto:freek@spatie.be">freek@spatie.be</a> with
                    questions about your data or this policy.
                </p>
                <h2>Why we use your information</h2>
                <p>
                    We use your information to provide your account, import and
                    display your collection, operate the social features you
                    use, send service emails, and respond to requests. We rely
                    on providing the service you ask for and our legitimate
                    interests in keeping it secure and reliable. Where consent
                    is required, you can withdraw it without affecting earlier
                    lawful processing.
                </p>
                <h2>Accounts and Google sign-in</h2>
                <p>
                    Plaat Club stores your email, display name, public handle
                    and a password hash, not your plain-text password. Optional
                    Google sign-in uses basic identity: your verified email,
                    name, stable Google identifier (sub), and profile picture
                    URL. We use these to sign you in, identify your account, and
                    display your avatar. Google access and refresh tokens are
                    not retained; we do not request Gmail or Drive access.
                </p>
                <p>
                    Existing password users must sign in and explicitly link
                    Google in Settings using their current password. Accounts
                    are never automatically linked just because email addresses
                    match.
                </p>
                <p>
                    We do not sell Google user data, use it for advertising, or
                    use it to train AI models. Our use of information received
                    from Google follows the Google API Services User Data
                    Policy, including its Limited Use requirements.
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
                    visibility before sending and sends no empty-week email. We
                    also send account verification, password recovery, and
                    friend-request emails. Friend-request emails include
                    personal signed links that allow a response without signing
                    in; do not forward them. Already delivered email cannot be
                    recalled, and viewers may copy information you share.
                </p>
                <h2>Service providers and security</h2>
                <p>
                    We use service providers for hosting, storage, email
                    delivery, and security, including Laravel Cloud for hosting
                    and Cloudflare for network services. They process the data
                    needed to provide those services. We may also disclose
                    information to meet a legal obligation or protect people and
                    the service. We do not sell or rent personal data.
                </p>
                <p>
                    We use HTTPS, hashed passwords, encrypted Discogs
                    credentials, and access controls to protect your
                    information. No online service can guarantee absolute
                    security. International transfers are subject to the
                    safeguards required by applicable data-protection law.
                </p>
                <h2>Cookies and technical information</h2>
                <p>
                    We use session and security cookies to keep you signed in
                    and protect requests. Technical records may include your IP
                    address, browser information, and service activity for
                    security and troubleshooting. Blocking necessary cookies can
                    prevent sign-in and other features from working. External
                    sites such as Google, Discogs, Apple Music, and Spotify have
                    their own privacy practices.
                </p>
                <h2>How long we keep information</h2>
                <p>
                    We retain information while your account is active or as
                    needed to provide the service. We may retain information
                    where required to meet legal obligations, resolve disputes,
                    or protect against misuse. If you request deletion, we
                    delete or anonymise the information we no longer need;
                    backup copies are removed through the backup lifecycle
                    rather than immediately.
                </p>
                <h2>Your requests</h2>
                <p>
                    You can ask to access, receive a copy of, correct, or delete
                    your personal data, or object to or restrict processing,
                    subject to applicable law. Email{' '}
                    <a href="mailto:freek@spatie.be">freek@spatie.be</a>.
                    Account deletion is handled by request, not a self-service
                    button. You can also complain to the Belgian Data Protection
                    Authority or your local supervisory authority.
                </p>
                <h2>Changes to this policy</h2>
                <p>
                    We publish changes on this page and update the date above.
                    We will notify you of significant changes where required.
                </p>
            </article>
        </>
    );
}
