<?php

use App\Mail\FriendsWeeklyDigest;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

it('sends exactly the previous seven days in original addition order and never repeats a completed week', function () {
    Mail::fake();
    $this->travelTo(now()->setDate(2026, 10, 19)->setTime(7, 0)); // Monday 09:00 Brussels (CEST).
    $viewer = socialUser('viewer');
    $owner = socialUser('owner', 'friends');
    socialFriends($viewer, $owner);
    socialRecord($owner, 'Before window', true, '2026-10-12 06:59:59', 1);
    socialRecord($owner, 'Window start', true, '2026-10-12 07:00:00', 2)->update(['shared_note' => 'Owner shared note', 'private_note' => 'Owner private note']);
    socialRecord($owner, 'Window end minus one', true, '2026-10-19 06:59:59', 3);
    socialRecord($owner, 'Next week', true, '2026-10-19 07:00:00', 4);
    socialRecord($owner, 'Initial import', false, '2026-10-18 12:00:00', 5);
    $this->artisan('social:send-digests')->assertSuccessful();
    Mail::assertSent(FriendsWeeklyDigest::class, function (FriendsWeeklyDigest $mail) use ($viewer) {
        expect($mail->records->pluck('payload.displayTitle')->all())->toBe(['Window end minus one', 'Window start']);
        $html = $mail->render();
        expect($html)->toContain('Window start', '/shelves/', '/settings', 'class="inner-body"', 'background-color:', 'See friends’ additions')
            ->not->toContain('Initial import', 'Before window', 'Next week', 'Owner private note');

        return $mail->hasTo($viewer->email);
    });
    Mail::assertSentCount(1);
    $this->artisan('social:send-digests')->assertSuccessful();
    Mail::assertSentCount(1);
});

it('delivers styled and plain text digests without interpreting collector metadata as markup', function () {
    $owner = socialUser('emailmetadata', 'everyone');
    $owner->update(['name' => '[Not a link](https://untrusted.example) <b>Collector</b>']);
    $record = socialRecord($owner, '<script>alert("title")</script> [Album](https://untrusted.example)');
    $record->update(['private_note' => 'Never include this private note']);
    $sent = Mail::mailer('array')->send((new FriendsWeeklyDigest($owner->records()->get()))->to('digest@example.test'));
    $message = $sent->getSymfonySentMessage()->getOriginalMessage();

    expect(html_entity_decode(strip_tags($message->getHtmlBody())))->toContain($owner->name, $record->payload['displayTitle']);
    expect($message->getHtmlBody())->toContain('class="inner-body"', '#f5f1e7', '#a64028')
        ->not->toContain('<script>', '<b>Collector</b>', 'href="https://untrusted.example"', 'Never include this private note');
    expect($message->getTextBody())->toContain('This week on your friends’ shelves · Plaat Club', $owner->name, $record->payload['displayTitle'], '/shelves/'.$record->id, '/settings')
        ->not->toContain('<p', '<table', 'Never include this private note');
});

it('rechecks privacy, friendship, preferences and verification at send time and suppresses empty weeks', function () {
    Mail::fake();
    $this->travelTo(now()->setDate(2026, 10, 19)->setTime(7, 0));
    $viewer = socialUser('viewer');
    $private = socialUser('private', 'friends');
    $removed = socialUser('removed', 'everyone');
    $pending = socialUser('pending', 'everyone');
    socialFriends($viewer, $private);
    $connection = socialFriends($removed, $viewer);
    socialRecord($private, 'Now private');
    socialRecord($removed, 'Unfriended');
    socialRecord($pending, 'Not accepted');
    $private->update(['visibility' => 'only_me']);
    $connection->delete();
    $this->artisan('social:send-digests')->assertSuccessful();
    Mail::assertNothingSent();

    $this->travel(7)->days();
    $private->update(['visibility' => 'friends']);
    socialRecord($private, 'Fresh but opted out', true, '2026-10-20 12:00:00', 456);
    $viewer->update(['digest_enabled' => false]);
    $this->artisan('social:send-digests')->assertSuccessful();
    Mail::assertNothingSent();
    $viewer->update(['digest_enabled' => true, 'email_verified_at' => null]);
    $this->artisan('social:send-digests')->assertSuccessful();
    Mail::assertNothingSent();
});

it('uses Brussels local week boundaries across daylight saving changes', function () {
    Mail::fake();
    $this->travelTo(now()->setDate(2026, 10, 26)->setTime(8, 0)); // Monday 09:00 CET after DST ends.
    $viewer = socialUser('viewer');
    $owner = socialUser('owner', 'everyone');
    socialFriends($viewer, $owner);
    socialRecord($owner, 'CEST start included', true, '2026-10-19 07:00:00', 1);
    socialRecord($owner, 'CET end included', true, '2026-10-26 07:59:59', 2);
    socialRecord($owner, 'CET next week', true, '2026-10-26 08:00:00', 3);
    $this->artisan('social:send-digests')->assertSuccessful();
    Mail::assertSent(FriendsWeeklyDigest::class, fn (FriendsWeeklyDigest $mail) => $mail->records->pluck('payload.displayTitle')->all() === ['CET end included', 'CEST start included']);
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'social:send-digests'));
    expect($event->expression)->toBe('0 9 * * 1')->and($event->timezone)->toBe('Europe/Brussels');
});

it('previews only the local signed-in recipient without advancing checkpoints and rechecks privacy', function () {
    Mail::fake();
    app()->detectEnvironment(fn () => 'local');
    config(['services.social_demo' => true]);
    $viewer = socialUser('viewer');
    $owner = socialUser('owner', 'friends');
    socialFriends($viewer, $owner);
    socialRecord($owner, 'Recent preview record', true, now()->subDay()->toDateTimeString());
    $this->withSession(['_token' => 'preview-test'])->actingAs($viewer)->post('/demo/digest', ['_token' => 'preview-test'])->assertRedirect();
    Mail::assertSent(FriendsWeeklyDigest::class, fn ($mail) => $mail->hasTo($viewer->email) && $mail->records->count() === 1);
    expect($viewer->fresh()->digest_week)->toBeNull();
    $owner->update(['visibility' => 'only_me']);
    $this->post('/demo/digest', ['_token' => 'preview-test'])->assertRedirect();
    Mail::assertSentCount(1);
    config(['services.social_demo' => false]);
    $this->post('/demo/digest', ['_token' => 'preview-test'])->assertNotFound();
    app()->detectEnvironment(fn () => 'production');
    config(['services.social_demo' => true]);
    $this->post('/demo/digest', ['_token' => 'preview-test'])->assertNotFound();
    $this->artisan('social:send-digests', ['--preview-for' => $viewer->id])->assertFailed();
    Mail::assertSentCount(1);
});
