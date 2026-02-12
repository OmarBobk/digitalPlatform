<?php

use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('contact page is accessible by anyone', function () {
    $this->get(route('contact'))
        ->assertOk()
        ->assertSee(__('main.contact_us'))
        ->assertSee(__('messages.send_us_a_message'))
        ->assertSee(__('messages.name'))
        ->assertSee(__('messages.message'));
});

test('contact page shows contact info when configured', function () {
    WebsiteSetting::instance()->update([
        'contact_email' => 'support@example.com',
        'primary_phone' => '+90 (555) 111-2233',
        'secondary_phone' => null,
    ]);

    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('support@example.com')
        ->assertSee('+90 (555) 111-2233')
        ->assertSee(__('messages.contact_info'));
});

test('contact form validates required fields', function () {
    Livewire::test('pages::frontend.contact')
        ->set('name', '')
        ->set('email', '')
        ->set('subject', '')
        ->set('message', '')
        ->call('submit')
        ->assertHasErrors(['name', 'email', 'subject', 'message']);
});

test('contact form validates email format', function () {
    Livewire::test('pages::frontend.contact')
        ->set('name', 'Jane')
        ->set('email', 'not-an-email')
        ->set('subject', 'Test')
        ->set('message', 'Hello')
        ->call('submit')
        ->assertHasErrors(['email']);
});

test('contact form submission sends email when contact email is configured', function () {
    Mail::fake();

    WebsiteSetting::instance()->update(['contact_email' => 'admin@store.com']);

    Livewire::test('pages::frontend.contact')
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('subject', 'Question')
        ->set('message', 'Hello, I have a question.')
        ->call('submit')
        ->assertHasNoErrors();

    Mail::assertSent(\App\Mail\ContactFormMail::class, function ($mail) {
        return $mail->senderName === 'John Doe'
            && $mail->senderEmail === 'john@example.com'
            && $mail->formSubject === 'Question'
            && $mail->messageBody === 'Hello, I have a question.';
    });
});

test('contact form shows error when contact email is not configured', function () {
    WebsiteSetting::instance()->update(['contact_email' => null]);

    Livewire::test('pages::frontend.contact')
        ->set('name', 'John')
        ->set('email', 'john@example.com')
        ->set('subject', 'Test')
        ->set('message', 'Message body')
        ->call('submit')
        ->assertHasErrors('form');
});
