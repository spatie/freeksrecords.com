import { Head, Link, usePage } from '@inertiajs/react';

import { FriendActions } from '@/modules/social/FriendActions';
import type { Person, SocialProps } from '@/modules/social/types';

type InvitationProps = {
    sender: Person;
    matches: boolean;
    friendship: { requested_by: number; accepted_at: string | null } | null;
    fulfilled: boolean;
};

export function InvitationPage({
    sender,
    matches,
    friendship,
    fulfilled,
}: InvitationProps) {
    const user = usePage<SocialProps>().props.auth.user;
    const incoming =
        friendship &&
        !friendship.accepted_at &&
        friendship.requested_by === sender.id;

    return (
        <>
            <Head title="You’re invited to Plaat Club" />
            <div className="page-heading">
                <p className="page-eyebrow">Good records. Better company.</p>
                <h1>You’re on the list.</h1>
            </div>
            <section className="panel stack">
                <h2>{sender.name} invited you to Plaat Club</h2>
                <p>
                    @{sender.handle} would love to share the joy of collecting
                    with you.
                </p>
                {!user ? (
                    <>
                        <p>
                            Create an account with the email address this
                            invitation was sent to, then verify it to receive
                            your friend request. No friendship starts without
                            your say-so.
                        </p>
                        <div className="actions">
                            <Link className="button" href="/register">
                                Join Plaat Club
                            </Link>
                            <Link className="button secondary" href="/login">
                                Sign in
                            </Link>
                        </div>
                    </>
                ) : !matches ? (
                    <p>
                        This invitation is for a different email address. Sign
                        in with the account it was sent to. You can find your
                        own requests on the friends page.
                    </p>
                ) : !user.verified ? (
                    <>
                        <p>
                            Verify your email address to receive {sender.name}’s
                            friend request.
                        </p>
                        <Link className="button" href="/verify-email">
                            Verify your email
                        </Link>
                    </>
                ) : friendship?.accepted_at ? (
                    <>
                        <p>You and {sender.name} are friends.</p>
                        <Link className="button" href={sender.profileUrl}>
                            View collection
                        </Link>
                    </>
                ) : incoming ? (
                    <>
                        <p>
                            {sender.name}’s friend request is waiting for you.
                            Accept to become friends, or decline if now isn’t
                            the time.
                        </p>
                        <FriendActions
                            personId={sender.id}
                            actions={[
                                { value: 'accept', label: 'Accept' },
                                { value: 'decline', label: 'Decline' },
                            ]}
                        />
                    </>
                ) : friendship ? (
                    <p>
                        Your friend request is pending. {sender.name} decides
                        whether to accept.
                    </p>
                ) : (
                    <p>
                        {fulfilled
                            ? 'This invitation has been processed. There is no pending request between you right now.'
                            : 'Your invitation is ready. Find your requests on the friends page.'}
                    </p>
                )}
                {user && <Link href="/friends">Go to friends</Link>}
                <p>
                    Becoming friends never changes your collection’s visibility.
                    Collections set to Only me stay private.
                </p>
            </section>
        </>
    );
}
