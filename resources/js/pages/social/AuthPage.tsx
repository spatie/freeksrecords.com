import { Head, Link, useForm, usePage } from '@inertiajs/react';

import { FieldError } from '@/modules/social/FieldError';
import { HeroArtwork } from '@/modules/social/HeroArtwork';
import type { AuthProps, SocialProps } from '@/modules/social/types';

const titles = {
    register: 'Create an account',
    forgot: 'Reset your password',
    reset: 'Choose a password',
    verify: 'Check your inbox',
    login: 'Sign in',
};
const welcomes = {
    register: 'Let ’em in.',
    forgot: 'Let’s get you back in.',
    reset: 'A fresh start.',
    verify: 'Check your inbox.',
    login: 'Welcome back.',
};
const introductions = {
    register:
        'Import your Discogs collection. Choose who you share it with. Get notified when your friends add something to theirs.',
    forgot: 'Enter your email address to receive a password reset link.',
    reset: '',
    verify: '',
    login: 'Your records. Your people. Your next good find.',
};

export function AuthPage({ artwork, mode, token = '', email = '' }: AuthProps) {
    const { auth, csrfToken, googleEnabled, demoUsers } =
        usePage<SocialProps>().props;
    const form = useForm({
        email,
        password: '',
        password_confirmation: '',
        token,
    });
    const action =
        mode === 'register'
            ? '/register'
            : mode === 'forgot'
              ? '/forgot-password'
              : mode === 'reset'
                ? '/reset-password'
                : '/login';
    const label =
        mode === 'register'
            ? 'Create account'
            : mode === 'forgot'
              ? 'Send reset link'
              : mode === 'reset'
                ? 'Save new password'
                : 'Sign in';
    return (
        <>
            <Head title={titles[mode]} />
            <div className="auth-layout">
                <HeroArtwork records={artwork} />
                <section
                    className="auth-panel"
                    aria-labelledby="auth-form-title"
                >
                    <div className="auth-slip-heading">
                        <span>
                            {mode === 'register' ? 'Membership' : 'Plaat Club'}
                        </span>
                        <span className="auth-slip-record" aria-hidden="true" />
                    </div>
                    <h1 id="auth-form-title">{welcomes[mode]}</h1>
                    {introductions[mode] && (
                        <p className="auth-introduction">
                            {introductions[mode]}
                        </p>
                    )}
                    {mode === 'verify' ? (
                        <>
                            <p>
                                We sent a link to{' '}
                                <strong>{auth.user?.email}</strong>. Open that
                                email and follow the link to verify your address
                                before using Plaat Club.
                            </p>
                            <p className="muted">
                                Can’t find it? Check your spam folder, or send a
                                fresh link.
                            </p>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.post(
                                        '/email/verification-notification',
                                    );
                                }}
                            >
                                <button
                                    className="button"
                                    disabled={form.processing}
                                >
                                    Resend verification email
                                </button>
                            </form>
                            <Link
                                className="text-button spaced"
                                href="/logout"
                                method="post"
                                as="button"
                            >
                                Wrong email? Sign out
                            </Link>
                        </>
                    ) : (
                        <>
                            {(mode === 'login' || mode === 'register') &&
                                googleEnabled && (
                                    <>
                                        <a
                                            className="button secondary google-button"
                                            href="/auth/google/redirect"
                                        >
                                            Continue with Google
                                        </a>
                                        <p className="auth-divider">
                                            or with email
                                        </p>
                                    </>
                                )}
                            <form
                                className="stack"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.post(action, {
                                        onFinish: () =>
                                            form.reset(
                                                'password',
                                                'password_confirmation',
                                            ),
                                    });
                                }}
                            >
                                <label htmlFor="email">
                                    Email
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        autoComplete="email"
                                        aria-invalid={Boolean(
                                            form.errors.email,
                                        )}
                                        aria-describedby={
                                            form.errors.email
                                                ? 'email-error'
                                                : undefined
                                        }
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                </label>
                                <FieldError
                                    id="email-error"
                                    message={form.errors.email}
                                />
                                {mode !== 'forgot' && (
                                    <>
                                        <label htmlFor="password">
                                            Password
                                            <input
                                                id="password"
                                                name="password"
                                                type="password"
                                                aria-invalid={Boolean(
                                                    form.errors.password,
                                                )}
                                                aria-describedby={
                                                    [
                                                        mode !== 'login'
                                                            ? 'password-help'
                                                            : '',
                                                        form.errors.password
                                                            ? 'password-error'
                                                            : '',
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' ') || undefined
                                                }
                                                autoComplete={
                                                    mode === 'login'
                                                        ? 'current-password'
                                                        : 'new-password'
                                                }
                                                minLength={
                                                    mode === 'login'
                                                        ? undefined
                                                        : 12
                                                }
                                                value={form.data.password}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'password',
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {mode !== 'login' && (
                                                <small id="password-help">
                                                    At least 12 characters. A
                                                    few words work well.
                                                </small>
                                            )}
                                        </label>
                                        <FieldError
                                            id="password-error"
                                            message={form.errors.password}
                                        />
                                        {mode === 'reset' && (
                                            <label htmlFor="password_confirmation">
                                                Confirm password
                                                <input
                                                    id="password_confirmation"
                                                    name="password_confirmation"
                                                    type="password"
                                                    autoComplete="new-password"
                                                    aria-invalid={Boolean(
                                                        form.errors
                                                            .password_confirmation,
                                                    )}
                                                    aria-describedby={
                                                        form.errors
                                                            .password_confirmation
                                                            ? 'password_confirmation-error'
                                                            : undefined
                                                    }
                                                    minLength={12}
                                                    value={
                                                        form.data
                                                            .password_confirmation
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'password_confirmation',
                                                            event.target.value,
                                                        )
                                                    }
                                                    required
                                                />
                                                <FieldError
                                                    id="password_confirmation-error"
                                                    message={
                                                        form.errors
                                                            .password_confirmation
                                                    }
                                                />
                                            </label>
                                        )}
                                    </>
                                )}
                                <button
                                    className="button"
                                    disabled={form.processing}
                                >
                                    {label}
                                </button>
                            </form>
                            {mode === 'login' ? (
                                <div className="auth-links">
                                    <Link href="/forgot-password">
                                        Forgot password?
                                    </Link>
                                    <Link href="/register">
                                        Create an account
                                    </Link>
                                </div>
                            ) : (
                                <p>
                                    {mode === 'reset'
                                        ? ''
                                        : mode === 'register'
                                          ? 'Already have an account?'
                                          : 'Remember your password?'}{' '}
                                    <Link href="/login">
                                        {mode === 'reset'
                                            ? 'Back to sign in'
                                            : 'Sign in'}
                                    </Link>
                                </p>
                            )}
                        </>
                    )}
                </section>
            </div>
            {mode === 'login' && demoUsers.length > 0 && (
                <section
                    className="demo-logins"
                    aria-label="Local demo account switcher"
                >
                    <p>
                        <strong>Local demo accounts</strong> — Switch without a
                        password
                    </p>
                    <div className="actions">
                        {demoUsers.map((demo) => (
                            <form
                                key={demo.email}
                                method="post"
                                action={demo.url}
                            >
                                <input
                                    type="hidden"
                                    name="_token"
                                    value={csrfToken}
                                />
                                <input
                                    type="hidden"
                                    name="email"
                                    value={demo.email}
                                />
                                <input
                                    type="hidden"
                                    name="redirect_url"
                                    value="/friends"
                                />
                                <button className="button secondary">
                                    {demo.label}
                                </button>
                            </form>
                        ))}
                    </div>
                    <small>
                        Alex has a full demo shelf. Casey’s collection is
                        private; Jamie’s shelf is empty.
                    </small>
                </section>
            )}
        </>
    );
}
