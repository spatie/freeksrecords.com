import { Head, Link, useForm } from '@inertiajs/react';

export function InviteFriendsPage() {
    const form = useForm({ email: '' });

    return (
        <>
            <Head title="Invite a friend" />
            <div className="page-heading">
                <p className="page-eyebrow">Good records. Better company.</p>
                <h1>Bring your people.</h1>
            </div>
            <section className="panel stack">
                <h2>Invite a friend by email</h2>
                <p>
                    Share the joy of collecting. Your friend gets an invitation
                    or a friend request if they’re already here. They choose
                    whether to accept.
                </p>
                <form
                    className="stack"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/friends/invite', {
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <label htmlFor="invite-email">
                        Your friend’s email address
                    </label>
                    <input
                        id="invite-email"
                        name="email"
                        type="email"
                        autoComplete="email"
                        required
                        maxLength={255}
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        aria-invalid={!!form.errors.email}
                        aria-describedby={
                            form.errors.email ? 'invite-error' : undefined
                        }
                    />
                    {form.errors.email && (
                        <p className="errors" role="alert" id="invite-error">
                            {form.errors.email}
                        </p>
                    )}
                    <div className="actions">
                        <button className="button" disabled={form.processing}>
                            {form.processing ? 'Sending…' : 'Send invitation'}
                        </button>
                        <Link href="/friends">Back to friends</Link>
                    </div>
                </form>
                <p>
                    Invitations last seven days. You can send a reminder after a
                    day. Your collection’s privacy stays exactly as you set it.
                </p>
            </section>
        </>
    );
}
