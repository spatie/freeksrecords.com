<?php

use App\Filament\Resources\ActivityResource;
use App\Filament\Resources\ActivityResource\Pages\ListActivities;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Widgets\CommunityStats;
use App\Models\Friendship;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('only allows the verified freek@spatie.be account into every admin page', function (string $identity, bool $verified, bool $allowed) {
    app()->detectEnvironment(fn () => 'local');
    $user = $identity === 'freek' ? User::freek() : socialUser($identity);
    $user->update(['email_verified_at' => $verified ? now() : null]);
    if ($identity === 'impostor') {
        $user->update(['legacy_owner' => true, 'name' => 'Freek Van der Herten']);
    }

    $this->actingAs($user);
    $allowed ? $this->get('/admin')->assertRedirect('/admin/users') : $this->get('/admin')->assertForbidden();
    $activity = activity()->causedBy($user)->log('Test activity');
    foreach (['/admin/users', '/admin/users/'.$user->id, '/admin/activities', '/admin/activities/'.$activity->id] as $path) {
        $response = $this->get($path);
        $allowed ? $response->assertOk() : $response->assertForbidden();
    }
})->with([
    ['freek', true, true],
    ['freek', false, false],
    ['collector', true, false],
    ['impostor', true, false],
]);

it('redirects guests to admin login and rejects non-admin credentials there', function () {
    $user = socialUser('collector');
    foreach (['/admin', '/admin/users', '/admin/users/'.$user->id, '/admin/activities'] as $path) {
        $this->get($path)->assertRedirect('/admin/login');
    }

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'a-long-demo-password'])
        ->call('authenticate')->assertHasFormErrors(['email']);
    $this->assertGuest();
});

it('shows a read-only searchable and filterable activity log with details', function () {
    $freek = User::freek();
    $freek->update(['email_verified_at' => now()]);
    $collector = socialUser('collector');
    $login = activity('authentication')->causedBy($collector)->performedOn($collector)->event('login')->log('Logged in');
    $import = activity('discogs')->causedBy($freek)->performedOn($freek)->event('initial_import_completed')
        ->withProperties(['records_count' => 42])->log('Initial collection import completed');
    $this->actingAs($freek);

    Livewire::test(ListActivities::class)
        ->assertCanSeeTableRecords([$login, $import])
        ->searchTable('Collector')->assertCanSeeTableRecords([$login])->assertCanNotSeeTableRecords([$import])
        ->searchTable('')->filterTable('log_name', 'discogs')->assertCanSeeTableRecords([$import])->assertCanNotSeeTableRecords([$login])
        ->resetTableFilters()->filterTable('event', 'login')->assertCanSeeTableRecords([$login])->assertCanNotSeeTableRecords([$import]);
    $this->get('/admin/activities/'.$import->id)->assertOk()->assertSee('records_count')->assertSee('42');
    expect(ActivityResource::canCreate())->toBeFalse()
        ->and(ActivityResource::canEdit($import))->toBeFalse()
        ->and(ActivityResource::canDelete($import))->toBeFalse()
        ->and(ActivityResource::canDeleteAny())->toBeFalse();
});

it('lists searchable collectors and shows accepted friends from either side of a friendship', function () {
    $freek = User::freek();
    $freek->update(['email_verified_at' => now()]);
    $first = socialUser('first');
    $collector = socialUser('collector');
    $last = socialUser('last');
    $pending = socialUser('pending');
    socialFriends($first, $collector);
    socialFriends($collector, $last);
    Friendship::query()->create(['user_low' => $collector->id, 'user_high' => $pending->id, 'requested_by' => $pending->id]);
    $this->actingAs($freek);

    Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$first, $collector, $last, $pending])
        ->searchTable('collector@example.test')->assertCanSeeTableRecords([$collector])
        ->assertCanNotSeeTableRecords([$first, $last, $pending]);
    $this->get('/admin/users/'.$collector->id)->assertOk()
        ->assertSee($first->name)->assertSee($last->name)->assertDontSee($pending->name)
        ->assertSee('/admin/users/'.$first->id)->assertSee('/admin/users/'.$last->id);
    $this->get('/admin/users/'.$pending->id)->assertSee('No accepted friendships yet.');
});

it('shows community totals and distinguishes accepted friendships from requests', function () {
    $freek = User::freek();
    $freek->update(['email_verified_at' => now()]);
    $first = socialUser('first');
    $second = socialUser('second');
    socialFriends($freek, $first);
    Friendship::query()->create(['user_low' => $first->id, 'user_high' => $second->id, 'requested_by' => $second->id]);
    $first->update(['sync_status' => 'Sync failed. Reconnect if access was revoked, then retry. Existing records were kept.']);
    $second->update(['created_at' => now()->subDays(8)]);

    $this->actingAs($freek);
    Livewire::test(CommunityStats::class)->assertSee('Collectors')->assertSee('2 joined in the last 7 days')
        ->assertSee('Friendships')->assertSee('1 pending request')->assertSee('1 failed sync')
        ->assertSee('Records');
});

it('impersonates a collector and restores Freek without granting the collector admin access', function (bool $verified) {
    $freek = User::freek();
    $freek->update(['email_verified_at' => now()]);
    $collector = socialUser('collector');
    $collector->update(['email_verified_at' => $verified ? now() : null]);
    $this->actingAs($freek)->withSession(['password_hash_web' => $freek->password]);

    Livewire::test(ViewUser::class, ['record' => $collector->id])
        ->callAction('impersonate')->assertRedirect('/');
    $this->assertAuthenticatedAs($collector);
    $this->get('/admin')->assertForbidden();
    $this->get($verified ? '/settings' : '/verify-email')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('impersonating', true)->where('adminUrl', null)->where('auth.user.id', $collector->id));

    $this->post('/admin/impersonation/stop')->assertRedirect('/admin/users')->assertSessionMissing('impersonator_id');
    $this->assertAuthenticatedAs($freek);
    $this->get('/admin/users')->assertOk();
    $this->get('/settings')->assertOk()->assertInertia(fn (Assert $page) => $page->where('impersonating', false)->where('adminUrl', '/admin'));
})->with([true, false]);

it('does not expose impersonation to other users or allow switching back without an admin session', function () {
    $collector = socialUser('collector');
    $this->post('/admin/impersonation/stop')->assertForbidden();
    $this->actingAs($collector)->post('/admin/impersonation/stop')->assertForbidden();
    $this->withSession(['impersonator_id' => $collector->id])->post('/admin/impersonation/stop')->assertForbidden();
    $this->assertAuthenticatedAs($collector);
});

it('rejects impersonation from a stale admin page after the signed-in user changes', function () {
    $freek = User::freek();
    $freek->update(['email_verified_at' => now()]);
    $collector = socialUser('collector');
    $this->actingAs($freek);
    $page = Livewire::test(ViewUser::class, ['record' => socialUser('other')->id]);

    $this->actingAs($collector);
    $page->callAction('impersonate')->assertForbidden();
    $this->assertAuthenticatedAs($collector);
});
