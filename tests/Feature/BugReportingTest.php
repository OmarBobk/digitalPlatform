<?php

use App\Events\BugInboxChanged;
use App\Models\Bug;
use App\Models\BugAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer']);
    Role::firstOrCreate(['name' => 'admin']);
    Permission::firstOrCreate(['name' => 'manage_bugs']);
});

it('requires screenshot before submitting bug report', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');
    $user->givePermissionTo('manage_bugs');
    actingAs($user);

    /** @var Testable $component */
    $component = Livewire::test('bugs.bug-report-form')
        ->set('scenario', 'notification')
        ->set('subtype', 'missing')
        ->set('severity', 'high')
        ->set('steps', ['Open topups page', 'Wait for notification']);

    $component->call('submit')
        ->assertHasErrors(['screenshots']);

    expect(Bug::query()->count())->toBe(0);
});

it('creates a bug with steps and attachment', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('customer');
    $user->givePermissionTo('manage_bugs');
    actingAs($user);

    Livewire::test('bugs.bug-report-form')
        ->set('scenario', 'topup_payment')
        ->set('subtype', 'request_failed')
        ->set('severity', 'medium')
        ->set('description', 'Topup failed after submit.')
        ->set('steps', ['Open wallet page', 'Submit topup form'])
        ->set('screenshots', [UploadedFile::fake()->image('bug.png')])
        ->call('submit')
        ->assertHasNoErrors();

    expect(Bug::query()->count())->toBe(1)
        ->and(Bug::query()->first()?->steps()->count())->toBe(2)
        ->and(Bug::query()->first()?->attachments()->count())->toBe(1);
});

it('hides quick report button without manage_bugs permission', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');
    actingAs($user);

    Livewire::test(\App\Livewire\Bugs\QuickReportButton::class)
        ->assertDontSee('Report Bug');
});

it('dispatches bug inbox broadcast when a bug is created', function () {
    Event::fake([BugInboxChanged::class]);
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('customer');
    $user->givePermissionTo('manage_bugs');
    actingAs($user);

    Livewire::test('bugs.bug-report-form')
        ->set('scenario', 'topup_payment')
        ->set('subtype', 'request_failed')
        ->set('severity', 'medium')
        ->set('description', 'Topup failed after submit.')
        ->set('steps', ['Open wallet page', 'Submit topup form'])
        ->set('screenshots', [UploadedFile::fake()->image('bug.png')])
        ->call('submit')
        ->assertHasNoErrors();

    Event::assertDispatched(BugInboxChanged::class, function (BugInboxChanged $event): bool {
        return $event->reason === 'created' && $event->bugId !== null;
    });
});

it('dispatches bug inbox broadcast when bug status is updated', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('manage_bugs');
    actingAs($admin);

    $bug = Bug::query()->create([
        'user_id' => $admin->id,
        'role' => 'admin',
        'scenario' => 'other',
        'subtype' => 'test',
        'severity' => 'low',
        'status' => Bug::STATUS_OPEN,
        'current_url' => 'https://example.test/page',
    ]);

    Event::fake([BugInboxChanged::class]);

    $bugId = $bug->id;

    Livewire::test('bugs.admin-show', ['bug' => $bug])
        ->set('status', Bug::STATUS_RESOLVED)
        ->call('updateStatus')
        ->assertHasNoErrors();

    Event::assertDispatched(BugInboxChanged::class, function (BugInboxChanged $event) use ($bugId): bool {
        return $event->reason === 'status-updated' && $event->bugId === $bugId;
    });
});

it('refreshes bug detail when bug inbox realtime event targets this bug', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('manage_bugs');
    actingAs($admin);

    $bug = Bug::query()->create([
        'user_id' => $admin->id,
        'role' => 'admin',
        'scenario' => 'other',
        'subtype' => 'test',
        'severity' => 'low',
        'status' => Bug::STATUS_OPEN,
        'current_url' => 'https://example.test/page',
    ]);

    /** @var Testable $component */
    $component = Livewire::test('bugs.admin-show', ['bug' => $bug]);
    expect($component->get('bug')->attachments)->toHaveCount(0);

    BugAttachment::query()->create([
        'bug_id' => $bug->id,
        'path' => 'bugs/test.png',
        'type' => 'image',
        'size' => 100,
    ]);

    $component->dispatch('bug-inbox-updated', bug_id: $bug->id, reason: 'created');

    expect($component->get('bug')->attachments)->toHaveCount(1);
});

it('bumps bug inbox realtime version on admin index when bug inbox event fires', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('manage_bugs');
    actingAs($admin);

    Livewire::test('bugs.admin-index')
        ->assertSet('inboxRealtimeVersion', 0)
        ->dispatch('bug-inbox-updated')
        ->assertSet('inboxRealtimeVersion', 1);
});
