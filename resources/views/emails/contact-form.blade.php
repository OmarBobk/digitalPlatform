<x-mail::message>
{{ __('messages.contact_form_email_intro') }}

<x-mail::panel>
<strong>{{ __('messages.name') }}:</strong> {{ $senderName }}<br>
<strong>{{ __('messages.email') }}:</strong> {{ $senderEmail }}<br>
<strong>{{ __('messages.subject') }}:</strong> {{ $formSubject }}
</x-mail::panel>

<strong>{{ __('messages.message') }}:</strong><br>
{!! nl2br(e($messageBody)) !!}
</x-mail::message>
