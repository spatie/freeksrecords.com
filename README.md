# Freek's Records

[![tests](https://github.com/freekmurze/freeksrecords.com/actions/workflows/tests.yml/badge.svg)](https://github.com/freekmurze/freeksrecords.com/actions/workflows/tests.yml)

The source of [freeksrecords.com](https://freeksrecords.com), a place to browse my vinyl collection. Records sit on a walnut shelf in a listening corner. Pull one out, read the (slightly worn) sleeve notes, and jump to any track on Spotify or Apple Music.

The collection is synced from [Discogs](https://www.discogs.com/user/freekmurze/collection). Every record and the collection as a whole get their own shareable URL with a generated social preview image.

## Why this exists

I've always loved music. When I was a student, I used to buy vinyl records and really enjoyed playing them. In the iPod era, I stopped getting vinyl because having your music always with you was just so nice.

Flash forward 15 to 20 years. Everything is digitized, and AI is sweeping the planet. Because I interact with the digital world so much, I value physical things again and enjoy doing stuff without involving computers.

So in the past year, I started collecting vinyl again, and I very much enjoy putting a record on each and every night I'm home. It has nothing to do with sound quality, but with the experience of looking through the collection, picking a record, holding it... making a conscious decision.

A couple of friends were asking for music suggestions, so I decided to create a nice site that lists my records so I can share it with them. I was already tracking my collection with Discogs, so the data comes from that API.

The site is made with Laravel, but I didn't write a single line of it. It was mainly an experiment in how easily an AI could build this and deploy it on [Laravel Cloud](https://cloud.laravel.com) (spoiler: it was very easy, and I was impressed).

All of this was done while listening to:

- _Shebang_ by Oren Ambarchi
- _How You Been_ by SML
- _Eureka_ by Jim O'Rourke

Happy browsing!

## Stack

- [Laravel 13](https://laravel.com) on PHP 8.5
- [Inertia v3](https://inertiajs.com) with React 19 and TypeScript
- [Tailwind CSS v4](https://tailwindcss.com)
- [Wayfinder](https://github.com/laravel/wayfinder) for typed routes on the frontend
- GD for rendering the Open Graph images
- [Vite+](https://viteplus.dev) for building, linting and formatting
- Pest, PHPStan (Larastan) and Pint

## Running it locally

```bash
git clone https://github.com/freekmurze/freeksrecords.com.git
cd freeksrecords.com
composer setup
composer dev
```

`composer setup` installs the PHP and JS dependencies, creates `.env`, generates an app key, migrates the SQLite database and builds the frontend. The site works straight away using the bundled snapshot in `resources/data/collection.json`.

## Syncing a collection

Point the app at a Discogs user in `.env`:

```dotenv
DISCOGS_USERNAME=freekmurze
DISCOGS_TOKEN=your-personal-access-token
```

A token is optional, but raises the Discogs rate limit considerably. Then use these commands:

| Command                            | What it does                                                              |
| ---------------------------------- | ------------------------------------------------------------------------- |
| `records:sync-discogs`             | Import new additions and remove records that left the collection          |
| `records:sync-dates --musicbrainz` | Store original and pressing dates, looking up missing ones on MusicBrainz |
| `records:repair-tracklists`        | Group movements that share a physical track                               |
| `records:copy-covers`              | Copy cover art into the public object storage disk                        |
| `records:export-snapshot`          | Export the collection and verified dates to the bundled snapshot          |
| `records:import-snapshot`          | Import the bundled snapshot into the configured database                  |

## Testing

```bash
composer test      # Pint, PHPStan and Pest
composer ci:check  # everything above, plus `vp check` and tsc
```

## Listening circle

The homepage welcomes visitors; signed-in collectors go straight to their friends’
additions, with their own collection and incoming requests alongside. Freek’s original
record room is at `/collection`; existing record URLs and old root browse fragments
continue to work. Visit `/community` to learn more or join. Accounts use email/password, inbox verification,
and password reset. Each profile has a unique handle at `/people/{handle}`.

- New collections default to **Only me**. **Friends** means accepted mutual friends;
  **Everyone** includes anonymous visitors. Hidden profiles expose only a name/handle,
  never record counts or Discogs connection details.
- Find friends by exact handle at `/friends`. Requests can be accepted, declined or
  cancelled; either friend can unfriend. Only accepted friends appear in `/feed`.
- Feed entries use the original Discogs addition date, newest first. First imports
  and migrated records never generate activity. Visibility and friendship are queried
  again on every visit, notification listing, and digest send.
- Any verified signed-in viewer with access can appreciate or comment on a specific
  collection copy. Authors can delete their comments even after losing access; collection
  owners can delete any comment or stop new comments. Existing comments remain visible
  when new comments are disabled. Notifications are in-app, not per-record emails.
- Discogs imports retain Vinyl, CD and Cassette formats, including mixed bundles.
  Profile collections can be filtered by format; unknown media stay visible under All.
  Format metadata refreshes on subsequent syncs without creating new activity.
- Owners can edit a shared and a private note per collection copy. Shared notes inherit
  collection privacy; private notes are owner-only and excluded from other viewers,
  feeds, notifications, digests and legacy APIs. Notes survive syncs; editing them sends
  no notifications and does not create feed activity.
- The weekly digest is enabled by default, can be disabled in Settings, and runs Monday
  at 09:00 **Europe/Brussels**. It covers the preceding local seven-day interval,
  including DST changes. No visible friend additions means no email. Sends are synchronous
  to avoid retaining stale private record payloads in an email queue. A completed week is
  recorded per recipient to prevent ordinary reruns from sending duplicates. As with
  SMTP generally, a crash after delivery but before marking completion can duplicate a send.

### Discogs OAuth

Register a Discogs application with callback `https://your-app-host/discogs/callback`.
Configure `DISCOGS_CONSUMER_KEY` and `DISCOGS_CONSUMER_SECRET` using your deployment
secret store (or Amp private secret input), never a committed file or chat message.
The connection UI fails safely with an explanatory message when these are absent.
OAuth callbacks are session-bound, expire after 15 minutes, and are single-use.
Access token pairs are encrypted with `APP_KEY` and never sent to the frontend.
Back up that key securely; rotating it requires reconnecting Discogs accounts.

Connections are verified against Discogs identity and each Discogs account can belong
to only one profile. Disconnect keeps imported records and stops sync; reconnect uses
the same Discogs identity so collections and conversations cannot accidentally mix.
Revoke the app on Discogs to revoke its grant as well.

`social:sync-collections` queues verified connected profiles daily at 04:15 Brussels,
or collectors can queue a manual sync in Settings. Jobs are unique and lock per owner.
Incomplete or interrupted imports keep existing records, remain resumable, and never
promote an initial import to activity. Removals only apply after complete pagination,
including a confirmed empty collection. Credentials are not serialized into jobs or errors.
Keep queue visibility/retry time greater than the job timeout: the database queue defaults
to 7500 seconds, with workers using `--timeout=7200`. Configure equivalent timeouts for
other queue backends. Large first imports take time because Discogs rate limits requests.

### Migration and Freek’s ownership

Back up the database and deploy in maintenance mode before `php artisan migrate --force`.
The social migration copies existing records to an owner-scoped table before swapping
it in. All payloads, copy IDs, dates and legacy URLs are preserved; the migration sends
no emails and creates no activity or notifications. It reserves **freek@spatie.be** with
an unknowable random password. Do not create a seeded production password.

Once real mail is configured, run `php artisan social:provision-freek` to send a standard
single-use password setup link only to that inbox. Then sign in and verify the email.
On snapshot-only installations, run `records:import-snapshot` before connecting Freek’s
Discogs account. The original shelf at `/collection`, `/record/...`, API and share-image routes remain public,
anonymous and edge-cacheable, and are always scoped to Freek. His collection remains
Everyone to honor those permanent public URLs and previously published artwork previews.
All other profiles and shelf pages are private/no-store, even when publicly visible;
no private collection share images are generated. The existing collection sync commands
remain Freek-only, and switch to his OAuth import after he connects.

### Amp orb preview

`.agents/setup` installs PHP 8.5/extensions, Composer, locked dependencies, SQLite and
compiled assets without overwriting an existing `.env`. It does not contact Discogs,
send external mail, or seed demo credentials automatically.

```bash
.agents/setup
php artisan records:import-snapshot
php artisan social:seed-demo
amp orb services ensure
amp orb portal 8000 --title 'Freeks Records social preview'
```

The supervised services are the web app, database queue worker, Laravel scheduler,
and Mailpit preview inbox. Mailpit captures SMTP locally; no preview emails leave the orb.
The web service uses its portal origin for app/assets URLs; share the exact generated
Portal URL, not a sandbox host or localhost address. Services survive pause/resume.
Setup/services become defaults for future orbs only after this branch reaches the
project’s default branch.

Local-only demo accounts: `alex@example.test`, `robin@example.test`, `sam@example.test`,
`casey@example.test`, and `jamie@example.test`, all with password `demo-listening-2026`.
Records, artwork and identities are synthetic, credentials are intentionally public,
and digests are disabled for demo users. Alex has accepted friends and an incoming
request from Jamie; Robin shares with friends, Sam with everyone, Casey with only
themselves. Alex’s own empty shelf illustrates onboarding. The seeder is idempotent
and refuses to run outside `APP_ENV=local`. The services configure SMTP to the local
Mailpit instance; open its generated inbox portal to verify signup and password reset.
In Settings, enable the digest and use **Check my digest preview** to exercise the
last seven days without changing the scheduled weekly checkpoint. This button is
available only with `APP_ENV=local` and `SOCIAL_DEMO_MODE=true`; privacy and empty-week
suppression still apply. Real Discogs authorization requires your own OAuth consumer
credentials; missing credentials show an actionable message rather than a fake connection.

## Deployment

This site runs on [Laravel Cloud](https://cloud.laravel.com). Every push to `main` is deployed automatically.

Production needs HTTPS, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, a working mail
transport, the queue worker, the scheduler (`schedule:run` every minute), and Discogs OAuth
secrets. Run `php artisan queue:restart` after deployments. The social preview does not
configure or deploy these production services, and a fake-HTTP OAuth test is not a live
Discogs authorization test.

## License

The code is open source under the MIT license. Album artwork belongs to its respective owners.
