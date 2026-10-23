# Plaat Club

[![tests](https://github.com/spatie/plaat.club/actions/workflows/tests.yml/badge.svg)](https://github.com/spatie/plaat.club/actions/workflows/tests.yml)

The source of [Plaat Club](https://plaat.club), a home for your vinyl, CDs and cassettes. Connect your Discogs collection, share your shelves, and discover what your friends are adding. Pull out a release, read its notes, and jump to any track on Spotify or Apple Music.

The site started with [Freek’s Discogs collection](https://www.discogs.com/user/freekmurze/collection), which still sits on its original walnut shelves at `/collection`. Its existing record URLs and generated social preview images remain available.

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
git clone https://github.com/spatie/plaat.club.git
cd plaat.club
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
| `records:generate-cover-previews`  | Queue missing 16×16 inline cover previews for all collectors              |
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

Signed-in accounts must verify their email before using the app, including opening
collection pages directly. Google sign-in automatically verifies the matching email
provided by Google. Anonymous visitors can still browse public collections.

- New collections default to **Only me**. **Friends** means accepted mutual friends;
  **Everyone** includes anonymous visitors. Hidden profiles expose only a name/handle,
  never record counts or Discogs connection details.
- Find friends by exact handle at `/friends`. Requests can be accepted, declined or
  cancelled; either friend can unfriend. Only accepted friends appear in `/feed`.
  Pending requests can also be answered inside the notification popover. New requests
  queue an email to verified recipients with seven-day Laravel signed links. Those
  links work without signing in, but require a confirmation POST: opening or scanning
  an email never changes a friendship. Responses and cancellations invalidate both
  choices, and an old link cannot act on a later request between the same people.
  Acceptance queues a confirmation email to the original requester after the transaction
  commits. Delivery is skipped if their email is unverified or that friendship has since
  been removed. Declining a request sends no email.
- Feed entries use the original Discogs addition date, newest first. First imports
  and migrated records never generate activity. Visibility and friendship are queried
  again on every visit, notification listing, and digest send.
- Any verified signed-in viewer with access can appreciate or comment on a specific
  collection copy. Authors can delete their comments even after losing access; collection
  owners can delete any comment or stop new comments. Existing comments remain visible
  when new comments are disabled. Notifications are in-app, not per-record emails.
- Discogs imports retain Vinyl, CD and Cassette formats, including mixed bundles.
  Collection shelves show sleeves, CD cases and cassette cases without a format filter.
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
On graceful worker shutdown (including Cloud deploys), imports release their job at the
next request boundary and resume on another worker, skipping already saved copies.
Runs also yield after 25 new copies, five listing pages, or a 60-second work budget
(checked between requests). Completed-page checkpoints are stored privately on the user;
an unfinished page is replayed safely. Checkpoints expire after a day, and changed listing
totals restart the scan without pruning. Copies keep their complete tracks and original-year
metadata. Public release/master responses are cached for a week across collectors; private
collection listings and OAuth responses are never cached. All Discogs API requests, including
OAuth and legacy imports, share an atomic cache lock and request budget. Authenticated requests
are spaced at least 1.1 seconds apart (2.5 seconds without authentication), in addition to response
time. Discogs' limit/used/remaining headers can lower the rate or pause requests for 60 seconds
when the budget is exhausted. HTTP 429 pauses all callers for at least 60 seconds; longer
`Retry-After` values (seconds or HTTP dates, also on 503) are respected. Long waits release import
jobs until the cooldown expires, retaining checkpoints without consuming exception retries.
Use a shared cache store such as database or Redis across all web and queue instances; local
file or array stores cannot coordinate separate servers. Connection errors and 5xx responses
receive up to three request attempts with backoff before queue-level retries.
Normal yields and deploy interruptions do not consume the three-exception retry budget; imports have a
24-hour retry deadline. Automatic retries are shown as retrying, not paused; only
exhausted retries require manual action. Diagnostics retain HTTP status or exception
class (and a cURL error number when available), never provider response bodies, OAuth headers or credentials.
Keep queue visibility/retry time greater than the job timeout: the database queue defaults
to 7500 seconds; collection jobs have a 300-second timeout (workers may still use
`--timeout=7200` for other jobs). Configure equivalent timeouts for
other queue backends. Forced kills cannot run cleanup and recover after that visibility
and lock timeout. Large first imports take time because Discogs rate limits requests.

### Migration and Freek’s ownership

Back up the database and deploy in maintenance mode before `php artisan migrate --force`.
The social migration copies existing records to an owner-scoped table before swapping
it in. All payloads, copy IDs, dates and legacy URLs are preserved; the migration sends
no emails and creates no activity or notifications. It reserves **freek@spatie.be** with
an unknowable random password. Do not create a seeded production password.

Once real mail is configured, run `php artisan social:provision-freek` to send a standard
single-use password setup link only to that inbox. Then sign in.
The `verify_existing_email_addresses` migration marks all existing accounts as verified
without sending email or overwriting previous verification dates. Future password-based
registrations still need inbox verification.
On snapshot-only installations, run `records:import-snapshot` before connecting Freek’s
Discogs account. The original shelf at `/collection`, `/record/...`, API and share-image routes remain public,
anonymous and edge-cacheable, and are always scoped to Freek. His collection remains
Everyone to honor those permanent public URLs and previously published artwork previews.
All other profiles and shelf pages are private/no-store, even when publicly visible;
no private collection share images are generated. The existing collection sync commands
remain Freek-only, and switch to his OAuth import after he connects.

### Admin

The Filament admin panel lives at `/admin`, also linked from Freek's account menu.
Only the verified `freek@spatie.be` account can enter it, including in local development.
Sign in with the site's existing account or the admin login form; Google sign-in remains
available through the site's login page. PHP's `intl` extension is required by Filament.
`composer install` publishes Filament's assets automatically.

The panel opens directly to the user list, with compact stats for collectors, records,
Discogs connections, friendships, comments and appreciations. Users are searchable by name, username or email, with
verification, connection and visibility filters. Each read-only profile shows collection
details and links to accepted friends; extra sync and email details are collapsed by default. “View as user” requires confirmation
and signs you into their account with their normal permissions: changes are real.
The warning banner's “Return to admin” restores Freek's session. Signing out instead
ends the whole session, including the ability to switch back without signing in again.

### Amp orb preview

`.agents/setup` installs PHP 8.5/extensions, Composer, locked dependencies, MySQL 8.4 and
compiled assets. Fresh orbs use an isolated MySQL database, matching the Laravel MySQL
engine attached to Plaat Club on Laravel Cloud; no production connection or data is used.
Existing `.env` files are preserved. SQLite remains available for the default test suite.
Setup does not contact Discogs, send external mail, or seed demo credentials automatically.

```bash
.agents/setup
php artisan records:import-snapshot
php artisan social:seed-demo
amp orb services ensure
amp orb portal 8000 --title 'Plaat Club social preview'
```

The supervised services are MySQL (loopback-only, not exposed through a portal), the web
app, database queue worker, Laravel scheduler, and Mailpit preview inbox. The disposable
local MySQL user is `plaat` with an empty password and access only to `plaat_club` and
`plaat_club_test`; never use these credentials outside the orb. Mailpit captures SMTP
locally; no preview emails leave the orb.
The web service uses its portal origin for app/assets URLs; share the exact generated
Portal URL, not a sandbox host or localhost address. Services survive pause/resume.
Setup/services become defaults for future orbs only after this branch reaches the
project’s default branch.

To run the PHP suite against MySQL instead of the default in-memory SQLite database:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=plaat_club_test DB_USERNAME=plaat DB_PASSWORD= php artisan test
```

Local-only demo accounts: `alex@example.test`, `robin@example.test`, `sam@example.test`,
`casey@example.test`, and `jamie@example.test`, all with password `demo-listening-2026`.
Identities are synthetic and credentials intentionally public; digests are disabled.
Shelves contain real releases: Alex 120, Robin 90, Sam 60, Casey 30. Vinyl is sampled
from the existing 850-release `resources/data/collection.json` snapshot. Eight real
CD/cassette editions in `resources/data/demo-editions.json` were verified against the
public Discogs release and master APIs on 2026-10-18 (source URLs and actual format,
year and track metadata are included). George Harrison, Pink Floyd and Bill Withers
editions reuse the existing local artwork for the same album, not edition-specific
packaging; artwork remains its owners’ property. Listening links for these editions
are honest search links. Seeding and tests need no network or Discogs credentials.
Recent additions populate activity alongside older initial-import records. Reruns
preserve edits, dates, notes and interactions, and upgrade only untouched legacy
synthetic payloads in place. Alex has accepted friends and an incoming
request from Jamie; Robin shares with friends, Sam with everyone, Casey with only
themselves. Alex’s own collection includes Vinyl, CD and Cassette releases, shared/private
notes, a comment and an appreciation, with corresponding in-app notifications. Jamie’s
empty shelf illustrates onboarding. The seeder is idempotent
and refuses to run outside `APP_ENV=local`. The services configure SMTP to the local
Mailpit instance; open its generated inbox portal to verify signup and password reset.
With development dependencies installed, only the login page shows Spatie Laravel Login Link
buttons for switching between these seeded accounts without passwords. Sign out first
to return to the login page and switch accounts. The endpoint
requires both `APP_ENV=local` and `SOCIAL_DEMO_MODE=true`, allows only existing synthetic
demo accounts, and restricts hosts to loopback and the configured app URL. It never
creates users or permits switching to Freek’s reserved account. Normal email/password
authentication remains unchanged; production installs use `composer install --no-dev`.
For developer email checks, enable a demo user’s digest and run
`php artisan social:send-digests --preview-for=<user-id>` locally. This checks the last
seven days without changing the weekly checkpoint; privacy and empty-week suppression
still apply. There are no preview tools in Settings. Real Discogs authorization requires your own OAuth consumer
credentials; missing credentials show an actionable message rather than a fake connection.

## Deployment

Plaat Club targets [Laravel Cloud](https://cloud.laravel.com). Creating its Cloud application
does not deploy this feature branch or migrate the original site. Configure deployment
and the production domain explicitly when the app is ready to launch.

Production needs HTTPS, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, a working mail
transport, the queue worker, the scheduler (`schedule:run` every minute), and Discogs OAuth
secrets. Run `php artisan queue:restart` after deployments. The social preview does not
configure or deploy these production services, and a fake-HTTP OAuth test is not a live
Discogs authorization test.

Before deploying shared releases, back up the database, use maintenance mode and pause
collection imports/queue workers: the old copy-level payload column is removed, so old
code must not run against the migrated schema. With the new code, first run:

```sh
php artisan migrate --force
```

Bring the app online and resume workers with the new code, then run the resumable metadata
import separately. Discogs rate limits can make this take hours; do not hold the site in
maintenance mode or block a deployment on this import:

```sh
php artisan records:import-discogs-info
```

The migration works offline: it moves core metadata, tracklists, artwork references, inline
cover previews, release notes and credits into one `releases` row per Discogs release ID. Different pressings remain
separate releases. Artist biographies are shared in `artists`, linked through `artist_release`.
Existing enrichment and cached artwork are retained; collection copy IDs, owners, dates,
personal notes, activity, comments, appreciations and URLs are unchanged. When copies disagree,
the oldest copy's metadata wins, with missing enrichment filled from other copies, cached
artwork preferred over Discogs image URLs, and resolved listening links preferred over searches.
Artwork files remain keyed by release ID, so copies share both the stored reference and file.
SQL browse projections and memberships are retained, and shared metadata edits refresh them
transactionally for every copy without changing personal addition dates or notes.

The import then fills missing artist biographies (with Discogs markup preserved), release
notes, and both release-level and track-level credits once per release. New collection imports
reuse shared releases and stored artist biographies. It leaves personal data, tracklists and
listening links alone, and checkpoints each release: if interrupted or rate-limited, rerun it
to resume. Empty biographies and notes are stored as empty rather than repeatedly fetched.
These fields are stored for future display; the UI currently only displays the attribution.

The follow-up metadata migration moves scalar release metadata out of `data` into typed
columns: titles, artist display name, years, partial release dates, label/catalogue,
formats, cover/source/preview, external links, release notes and Discogs master/primary-label
IDs. `releases.id` and `artists.id` remain the official Discogs IDs. Genre and style names
have no Discogs numeric IDs. Arrays (including all label objects with their Discogs IDs),
tracklists and credits remain JSON; collection browsing keeps its SQL projections.
Years, master/label IDs, MusicBrainz IDs and the main text lookup fields are indexed.
Partial dates stay strings so a known year or year/month is not turned into an invented day.
For example, `Release::where('year', 1981)->where('discogs_label_id', 26126)->get()`
uses ordinary columns, without extracting JSON.

Own record, cover, friend-action, comment and admin URLs use random UUIDv4 public IDs.
Numeric primary/foreign keys stay internal; profile handles and official Discogs links
are unchanged. Old numeric URLs and previously emailed numeric signed links return 404
rather than disclosing their replacement. Existing access policies still apply.

Before running this migration, stop the old Discogs metadata command as well as imports
and workers, then run `php artisan down --render="errors::503" --retry=60`. The migration
preserves enrichment and timestamps but resets the information-import checkpoint so the
resumable import also fills the newly stored master/label IDs. Restart that command with
the new code after the deployment; already cached public responses and stored biographies
are reused. Laravel Cloud replaces the app container on deployment, so reapply maintenance
mode to the new container if verification must finish before reopening the site.

After deploying inline cover previews, run `php artisan records:generate-cover-previews`
once, with the queue worker running. It is safe to rerun (or use `--limit=100`): only
missing previews are queued, and identical artwork reuses an existing preview.
For command runtimes with short execution limits, process bounded batches with
`--limit=500 --after-id=<last logged record ID>` to resume the scan.
The artwork-only cache holds one tiny preview per source, never collection metadata.
New Discogs imports queue previews automatically. The GD pipeline stores a 16×16 JPEG
data URI once in shared release data, exposed through each copy's virtual record payload;
collection rendering only serializes that value and
never downloads or generates artwork. Failed jobs can be retried without losing records.
No separate preview migration is needed. Social collection responses retain their existing
visibility checks and private/no-store headers; previews do not publish private artwork.

### Outgoing email

Production uses Postmark with `MAIL_MAILER=postmark`, `POSTMARK_API_KEY` set to the
Postmark Server API Token, `MAIL_FROM_ADDRESS=hello@plaat.club`, and
`MAIL_FROM_NAME="Plaat Club"`. Set these as custom variables in Laravel Cloud, then
deploy the commit containing the Postmark transport dependencies. Custom variables
override the attached Resend integration's injected mail settings. Keep Resend attached
until delivery through Postmark has been checked. Local previews continue using Mailpit.

## License

The code is open source under the MIT license. Album artwork belongs to its respective owners.
