import { Head, useForm } from '@inertiajs/react';

export function FriendRequestResponsePage({
    sender,
    action,
    responseUrl,
}: {
    sender: string | null;
    action: 'accept' | 'decline';
    responseUrl: string;
}) {
    const form = useForm({});
    const label =
        action === 'accept'
            ? 'Accept friend request'
            : 'Decline friend request';

    return (
        <>
            <Head title="Friend request" />
            <section className="onboarding">
                <p className="page-eyebrow">Your listening circle</p>
                <h1>{sender ? 'Good company?' : 'All taken care of.'}</h1>
                {sender ? (
                    <>
                        <p className="lede">
                            @{sender} sent you a friend request.
                        </p>
                        <p>
                            Friends can see collections shared with Friends.
                            Only me stays private.
                        </p>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(responseUrl);
                            }}
                        >
                            <button
                                className="button"
                                disabled={form.processing}
                            >
                                {label}
                            </button>
                        </form>
                    </>
                ) : (
                    <p>
                        This request is no longer pending. It may have been
                        accepted, declined, or cancelled.
                    </p>
                )}
            </section>
        </>
    );
}
