<?php

use App\Jobs\SyncUserCollection;
use App\Support\DiscogsRecordMapper;
use App\Support\RecordCollection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    config(['services.discogs.consumer_key' => 'test-consumer', 'services.discogs.consumer_secret' => 'test-secret']);
});

it('completes session-bound single-use OAuth, verifies identity, and encrypts tokens at rest', function () {
    Bus::fake();
    $user = socialUser('oauth');
    Http::fake([
        'api.discogs.com/oauth/request_token' => Http::response('oauth_token=request-token&oauth_token_secret=request-secret&oauth_callback_confirmed=true'),
        'api.discogs.com/oauth/access_token' => Http::response('oauth_token=access-token&oauth_token_secret=access-secret'),
        'api.discogs.com/oauth/identity' => Http::response(['username' => 'discogs-owner']),
    ]);
    $this->actingAs($user)->post('/discogs/connect')->assertRedirect('https://www.discogs.com/oauth/authorize?oauth_token=request-token');
    $this->get('/discogs/callback?oauth_token=request-token&oauth_verifier=verified')->assertRedirect('/settings')->assertSessionHasNoErrors();
    $fresh = $user->fresh();
    expect($fresh->discogs_username)->toBe('discogs-owner')->and($fresh->discogs_token)->toBe('access-token');
    $stored = DB::table('users')->where('id', $user->id)->first();
    expect($stored->discogs_token)->not->toBe('access-token')->and($stored->discogs_secret)->not->toBe('access-secret');
    expect($fresh->toArray())->not->toHaveKeys(['discogs_token', 'discogs_secret', 'email', 'password']);
    Bus::assertDispatched(SyncUserCollection::class, fn ($job) => $job->userId === $user->id);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.discogs.com/oauth/access_token' && $request->method() === 'POST' && str_contains($request->header('Authorization')[0], 'oauth_verifier="verified"') && str_contains($request->header('Authorization')[0], 'oauth_signature_method="HMAC-SHA1"'));
    $this->get('/discogs/callback?oauth_token=request-token&oauth_verifier=verified')->assertForbidden();
    Http::assertSentCount(3);
    $this->delete('/discogs')->assertRedirect();
    expect($user->fresh()->discogs_token)->toBeNull()->and($user->fresh()->discogs_username)->toBe('discogs-owner');
});

it('rejects forged, expired and cross-user callbacks without reaching Discogs', function (string $scenario) {
    $user = socialUser('oauth');
    $this->actingAs($user)->withSession(['discogs_pending' => [
        'token' => 'expected', 'secret' => Crypt::encryptString('secret'),
        'user_id' => $scenario === 'other_user' ? $user->id + 1 : $user->id,
        'expires' => now()->addMinutes($scenario === 'expired' ? -1 : 15)->timestamp,
    ]])->get('/discogs/callback?oauth_token='.($scenario === 'forged' ? 'forged' : 'expected').'&oauth_verifier=verified')->assertForbidden();
    Http::assertNothingSent();
})->with(['forged', 'expired', 'other_user']);

it('does not attach a Discogs identity that already belongs to another profile', function () {
    Bus::fake();
    socialUser('existing')->update(['discogs_username' => 'already-connected']);
    $user = socialUser('newcomer');
    Http::fake([
        'api.discogs.com/oauth/access_token' => Http::response('oauth_token=access&oauth_token_secret=secret'),
        'api.discogs.com/oauth/identity' => Http::response(['username' => 'already-connected']),
    ]);
    $this->actingAs($user)->withSession(['discogs_pending' => ['token' => 'expected', 'secret' => Crypt::encryptString('secret'), 'user_id' => $user->id, 'expires' => now()->addMinutes(15)->timestamp]])
        ->get('/discogs/callback?oauth_token=expected&oauth_verifier=verified')->assertRedirect('/settings')->assertSessionHasErrors('discogs');
    expect($user->fresh()->discogs_token)->toBeNull();
    Bus::assertNothingDispatched();
});

it('imports paginated collections silently, isolates duplicate copy ids, then mirrors additions and removals', function () {
    $user = socialUser('sync');
    $user->update(['discogs_username' => 'collector', 'discogs_token' => 'test-access', 'discogs_secret' => 'test-access-secret']);
    $other = socialUser('other');
    socialRecord($other, 'Other copy', instance: 101);
    $entry = discogsCollectionEntry(101, 201);
    $entry['basic_information']['master_id'] = 0;
    $second = [...$entry, 'instance_id' => 102];
    $newEntry = [...$entry, 'instance_id' => 103, 'date_added' => '2026-10-18T09:30:00+02:00'];
    Http::fake([
        'api.discogs.com/users/*' => Http::sequence()
            ->push(['pagination' => ['pages' => 2, 'items' => 2], 'releases' => [$entry]])
            ->push(['pagination' => ['pages' => 2, 'items' => 2], 'releases' => [$second]])
            ->push(['pagination' => ['pages' => 1, 'items' => 2], 'releases' => [$second, $newEntry]])
            ->push(['pagination' => ['pages' => 1, 'items' => 2], 'releases' => [$second, $newEntry]])
            ->push(['pagination' => ['pages' => 1, 'items' => 0], 'releases' => []]),
        'api.discogs.com/releases/*' => Http::response(['year' => 1987, 'tracklist' => [['title' => 'Song', 'position' => 'A1']]]),
    ]);
    $job = new SyncUserCollection($user->id);
    $job->handle(app(DiscogsRecordMapper::class), app(RecordCollection::class));
    expect($user->records()->count())->toBe(2)->and($user->records()->where('is_activity', true)->count())->toBe(0)->and($user->fresh()->collection_imported_at)->not->toBeNull();
    $job->handle(app(DiscogsRecordMapper::class), app(RecordCollection::class));
    $job->handle(app(DiscogsRecordMapper::class), app(RecordCollection::class));
    expect($user->records()->pluck('instance_id')->all())->toBe([102, 103])
        ->and($user->records()->where('is_activity', true)->pluck('instance_id')->all())->toBe([103]);
    expect($user->records()->where('instance_id', 103)->first()->added_at->format('Y-m-d H:i:s'))->toBe('2026-10-18 07:30:00');
    expect($other->records()->first()->payload['title'])->toBe('Other copy');
    $job->handle(app(DiscogsRecordMapper::class), app(RecordCollection::class));
    expect($user->records()->count())->toBe(0)->and($other->records()->count())->toBe(1);
});

it('preserves collections and initial-import suppression through partial responses and interrupted retries', function () {
    $user = socialUser('partial');
    $user->update(['discogs_username' => 'partial', 'discogs_token' => 'access', 'discogs_secret' => 'secret']);
    $entry = discogsCollectionEntry(301, 401);
    $entry['basic_information']['master_id'] = 0;
    $second = [...$entry, 'instance_id' => 302];
    socialRecord($user, 'Kept after failure', false, instance: 303);
    Http::fake([
        'api.discogs.com/users/*' => Http::sequence()
            ->push(['pagination' => ['pages' => 1, 'items' => 2], 'releases' => [$entry]])
            ->push(['pagination' => ['pages' => 1, 'items' => 2], 'releases' => [$entry, $second]])
            ->push([], 503),
        'api.discogs.com/releases/*' => Http::response(['tracklist' => []]),
    ]);
    $job = new SyncUserCollection($user->id);
    expect(fn () => $job->handle(app(DiscogsRecordMapper::class), app(RecordCollection::class)))->toThrow(RuntimeException::class);
    expect($user->records()->count())->toBe(2)->and($user->fresh()->collection_imported_at)->toBeNull();
    $job->handle(app(DiscogsRecordMapper::class), app(RecordCollection::class));
    expect($user->records()->count())->toBe(2)->and($user->records()->where('is_activity', true)->count())->toBe(0);
    expect(fn () => $job->handle(app(DiscogsRecordMapper::class), app(RecordCollection::class)))->toThrow(RuntimeException::class);
    expect($user->records()->count())->toBe(2);
});

it('fails safely without OAuth configuration or with malformed provider responses', function () {
    Bus::fake();
    $user = socialUser('missingconfig');
    config(['services.discogs.consumer_key' => null]);
    $this->actingAs($user)->from('/settings')->post('/discogs/connect')->assertRedirect('/settings')->assertSessionHasErrors('discogs');
    Http::assertNothingSent();
    config(['services.discogs.consumer_key' => 'test-consumer']);
    Http::fake(['api.discogs.com/oauth/request_token' => Http::response('oauth_token[]=unexpected&oauth_token_secret=secret&oauth_callback_confirmed=true')]);
    $this->post('/discogs/connect')->assertRedirect('/settings')->assertSessionHasErrors('discogs');
    $this->get('/discogs/callback?oauth_token=unexpected&oauth_verifier=test')->assertForbidden();
    expect($user->fresh()->discogs_token)->toBeNull();
    Bus::assertNothingDispatched();
});

it('refuses to mix a different Discogs account into an existing shelf', function () {
    Bus::fake();
    $user = socialUser('reconnect');
    $user->update(['discogs_username' => 'original-account']);
    socialRecord($user, 'Keep my original record');
    Http::fake([
        'api.discogs.com/oauth/access_token' => Http::response('oauth_token=new-access&oauth_token_secret=new-secret'),
        'api.discogs.com/oauth/identity' => Http::response(['username' => 'different-account']),
    ]);
    $this->actingAs($user)->withSession(['discogs_pending' => ['token' => 'expected', 'secret' => Crypt::encryptString('secret'), 'user_id' => $user->id, 'expires' => now()->addMinutes(15)->timestamp]])
        ->get('/discogs/callback?oauth_token=expected&oauth_verifier=verified')->assertRedirect('/settings')->assertSessionHasErrors('discogs');
    expect($user->fresh()->discogs_username)->toBe('original-account')->and($user->fresh()->discogs_token)->toBeNull();
    expect($user->records()->first()->payload['displayTitle'])->toBe('Keep my original record');
    Bus::assertNothingDispatched();
});
