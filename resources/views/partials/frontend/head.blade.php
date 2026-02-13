<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@php
    $loyaltyTierLabels = [
        'bronze' => __('messages.loyalty_tier_bronze'),
        'silver' => __('messages.loyalty_tier_silver'),
        'gold' => __('messages.loyalty_tier_gold'),
    ];
@endphp
<script>
    window.Laravel = window.Laravel || {};
    @auth
    window.Laravel.userId = {{ auth()->id() }};
    window.Laravel.isAdmin = @json(auth()->user()?->hasRole('admin'));
    @endauth
    window.Laravel.loyaltyTierLabels = @json($loyaltyTierLabels);
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
