import { Head, useForm, usePage } from '@inertiajs/react';

import { FieldError } from '@/modules/social/FieldError';
import { ImportStatus } from '@/modules/social/ImportStatus';
import { SocialSwitch } from '@/modules/social/SocialSwitch';
import type { SocialProps } from '@/modules/social/types';

export function SettingsPage() {
    const { auth, csrfToken, googleEnabled } = usePage<SocialProps>().props;
    const user = auth.user!;
    const form = useForm({
        handle: user.handle,
        visibility: user.visibility,
        comments_enabled: user.commentsEnabled,
        digest_enabled: user.digestEnabled,
    });
    const connection = useForm({});
    return (
        <>
            <Head title="Your preferences" />
            <div className="page-heading">
                <h1>Settings</h1>
            </div>
            <div className="two-columns">
                <section className="panel">
                    <h2>Profile & privacy</h2>
                    <form
                        className="stack"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.patch('/settings');
                        }}
                    >
                        <label htmlFor="handle">
                            Username
                            <input
                                id="handle"
                                name="handle"
                                aria-invalid={Boolean(form.errors.handle)}
                                aria-describedby={`handle-help${form.errors.handle ? ' handle-error' : ''}`}
                                value={form.data.handle}
                                onChange={(event) =>
                                    form.setData('handle', event.target.value)
                                }
                                pattern="[a-zA-Z0-9_]{3,30}"
                                minLength={3}
                                maxLength={30}
                                required
                            />
                            <small id="handle-help">
                                3–30 letters, numbers or underscores. Your
                                collection: plaat.club/{user.handle}
                            </small>
                        </label>
                        <FieldError
                            id="handle-error"
                            message={form.errors.handle}
                        />
                        <label htmlFor="visibility">
                            Who can see your collection?
                            <select
                                id="visibility"
                                name="visibility"
                                aria-invalid={Boolean(form.errors.visibility)}
                                aria-describedby={`visibility-help${form.errors.visibility ? ' visibility-error' : ''}`}
                                value={form.data.visibility}
                                onChange={(event) =>
                                    form.setData(
                                        'visibility',
                                        event.target
                                            .value as typeof user.visibility,
                                    )
                                }
                            >
                                {!user.legacyOwner && (
                                    <>
                                        <option value="only_me">Only me</option>
                                        <option value="friends">Friends</option>
                                    </>
                                )}
                                <option value="everyone">Everyone</option>
                            </select>
                            <small id="visibility-help">
                                {user.legacyOwner
                                    ? 'Freek’s original collection stays public to preserve existing links and previews.'
                                    : 'Friends means accepted mutual friends. Everyone includes visitors who are not signed in. Private profiles show only your name and handle.'}
                            </small>
                        </label>
                        <FieldError
                            id="visibility-error"
                            message={form.errors.visibility}
                        />
                        <SocialSwitch
                            checked={form.data.comments_enabled}
                            description="Switching this off stops new comments. Existing comments remain visible and can be deleted."
                            label="Allow comments on my records"
                            name="comments_enabled"
                            onChange={(checked) =>
                                form.setData('comments_enabled', checked)
                            }
                        />
                        <SocialSwitch
                            checked={form.data.digest_enabled}
                            description="Mondays at 09:00 Europe/Brussels, only when there are new records. Includes only records you can currently view."
                            label="Email me my friends’ new records"
                            name="digest_enabled"
                            onChange={(checked) =>
                                form.setData('digest_enabled', checked)
                            }
                        />
                        <button className="button" disabled={form.processing}>
                            Save preferences
                        </button>
                    </form>
                </section>
                <section className="panel" id="discogs">
                    <h2>Discogs</h2>
                    {user.discogsConnected ? (
                        <>
                            <p>
                                Connected as{' '}
                                <strong>{user.discogsUsername}</strong>.
                            </p>
                            {!user.importedAt ? (
                                <ImportStatus user={user} />
                            ) : (
                                <>
                                    <p>
                                        {user.syncStatus ?? 'Ready to import'}
                                        <br />
                                        <small>
                                            Last successful sync:{' '}
                                            {user.lastSyncedAt
                                                ? new Intl.DateTimeFormat(
                                                      'en-GB',
                                                      {
                                                          dateStyle: 'medium',
                                                          timeStyle: 'short',
                                                          timeZone:
                                                              'Europe/Brussels',
                                                      },
                                                  ).format(
                                                      new Date(
                                                          user.lastSyncedAt,
                                                      ),
                                                  )
                                                : 'Not yet'}
                                        </small>
                                    </p>
                                    <button
                                        className="button"
                                        disabled={connection.processing}
                                        onClick={() =>
                                            connection.post('/discogs/sync')
                                        }
                                    >
                                        Sync collection now
                                    </button>
                                </>
                            )}
                            <div className="spaced">
                                <p>
                                    Disconnecting stops syncing but keeps your
                                    records. Reconnect the same Discogs account
                                    to resume.
                                </p>
                                <button
                                    className="button secondary"
                                    disabled={connection.processing}
                                    onClick={() =>
                                        connection.delete('/discogs')
                                    }
                                >
                                    Disconnect Discogs
                                </button>
                            </div>
                        </>
                    ) : (
                        <>
                            <p>
                                Connect your Discogs account to import your
                                collection. We never ask for your Discogs
                                password.
                            </p>
                            <form method="post" action="/discogs/connect">
                                <input
                                    type="hidden"
                                    name="_token"
                                    value={csrfToken}
                                />
                                <button className="button">
                                    {user.discogsUsername
                                        ? 'Reconnect Discogs'
                                        : 'Connect Discogs'}
                                </button>
                            </form>
                        </>
                    )}
                </section>
            </div>
            {googleEnabled && (
                <section
                    className="panel spaced google-settings"
                    aria-labelledby="google-sign-in-title"
                >
                    <h2 id="google-sign-in-title">Google sign-in</h2>
                    {user.googleConnected ? (
                        <>
                            <p>Sign in with your connected Google account.</p>
                            <p className="muted">
                                To set or reset a password, sign out and choose
                                “Forgot password?” on the sign-in page.
                            </p>
                        </>
                    ) : (
                        <>
                            <p>
                                Use Google to sign in to this account. Confirm
                                your current password, then choose the Google
                                account for <strong>{user.email}</strong>.
                            </p>
                            <form
                                className="stack"
                                method="post"
                                action="/settings/google"
                            >
                                <input
                                    type="hidden"
                                    name="_token"
                                    value={csrfToken}
                                />
                                <label htmlFor="current_password">
                                    Current password
                                    <input
                                        id="current_password"
                                        name="current_password"
                                        type="password"
                                        autoComplete="current-password"
                                        required
                                    />
                                </label>
                                <button className="button secondary google-button">
                                    Connect Google
                                </button>
                            </form>
                            <p className="muted spaced">
                                Your password will still work. Google never gets
                                your Plaat Club password, and we don’t access
                                your Gmail or Drive.
                            </p>
                        </>
                    )}
                </section>
            )}
        </>
    );
}
