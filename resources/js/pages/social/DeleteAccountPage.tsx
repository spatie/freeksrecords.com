import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';

import { FieldError } from '@/modules/social/FieldError';
import type { SocialProps } from '@/modules/social/types';
import '../../../css/delete-account.css';

export function DeleteAccountPage() {
    const user = usePage<SocialProps>().props.auth.user!;
    const form = useForm({ confirmation: '' });

    return (
        <>
            <Head title="Delete your account" />
            <div className="delete-account-stage">
                <div className="delete-account-intro">
                    <p className="delete-eyebrow">
                        Plaat Club / The final track
                    </p>
                    <h1>
                        One last
                        <br />
                        spin.
                    </h1>
                    <div className="delete-vinyl" aria-hidden="true">
                        <span>
                            Plaat Club<strong>Side B</strong>
                            <i />
                        </span>
                    </div>
                </div>
                <section
                    className="delete-confirmation"
                    aria-labelledby="delete-title"
                >
                    <p className="delete-eyebrow">
                        This is not a pause button.
                    </p>
                    <h2 id="delete-title">Delete your account?</h2>
                    <p>
                        This permanently deletes your Plaat Club account and
                        imported collection. Your comments, appreciations,
                        friendships and notifications go too — including your
                        comments on other people’s records.
                    </p>
                    <p className="delete-discogs-note">
                        Your records on Discogs stay exactly where they are. We
                        won’t change or delete anything there.
                    </p>
                    <form
                        className="stack"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.delete('/settings/account');
                        }}
                    >
                        <label htmlFor="delete-confirmation">
                            <span>
                                Type <strong>{user.handle}</strong> to confirm
                            </span>
                            <input
                                id="delete-confirmation"
                                name="confirmation"
                                autoComplete="off"
                                autoCapitalize="none"
                                spellCheck={false}
                                required
                                value={form.data.confirmation}
                                onChange={(event) =>
                                    form.setData(
                                        'confirmation',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.confirmation)}
                                aria-describedby={
                                    form.errors.confirmation
                                        ? 'delete-warning delete-error'
                                        : 'delete-warning'
                                }
                            />
                        </label>
                        <p id="delete-warning" className="delete-warning">
                            There’s no undo. Please be sure before you drop the
                            needle.
                        </p>
                        <FieldError
                            id="delete-error"
                            message={form.errors.confirmation}
                        />
                        <Link className="button delete-keep" href="/settings">
                            <ArrowLeft size={18} />
                            Keep my account
                        </Link>
                        <button
                            className="button delete-permanent"
                            disabled={
                                form.processing ||
                                form.data.confirmation !== user.handle
                            }
                        >
                            <Trash2 size={18} />
                            {form.processing
                                ? 'Deleting account…'
                                : 'Permanently delete account'}
                        </button>
                    </form>
                </section>
            </div>
        </>
    );
}
