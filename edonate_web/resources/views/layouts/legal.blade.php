<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | eDonate</title>
    <x-privacy-assets />
</head>
<body class="legal-page">
    <a class="ed-skip-link" href="#main-content">Skip to main content</a>
    <header class="legal-shell"><a href="{{ url('/') }}">eDonate home</a></header>
    <main id="main-content" class="legal-shell" tabindex="-1">
        <h1>@yield('title')</h1>
        <p>Policy version: {{ config('privacy.version') }}</p>
        @unless(config('privacy.reviewed') && config('privacy.controller') && config('privacy.address') && config('privacy.contact') && config('privacy.representative'))
            <p class="legal-draft" role="note">Draft notice awaiting the operator's substantive legal-basis and governance review. This is not a certification of legal compliance. Do not submit real personal or health information until the operator publishes the reviewed notice.</p>
        @endunless
        @yield('policy')
        <section aria-labelledby="legal-contact">
            <h2 id="legal-contact">Operator and privacy contact</h2>
            <p>Responsible organization: {{ config('privacy.controller') ?: 'Not yet supplied by the operator' }}.</p>
            <p>Postal address: {{ config('privacy.address') ?: 'Not yet supplied by the operator' }}.</p>
            <p>Privacy representative: {{ config('privacy.representative') ?: 'Not yet supplied by the operator' }}@if(config('privacy.representative_title')) ({{ config('privacy.representative_title') }})@endif.</p>
            <p>Service territory: {{ config('privacy.philippines_only') ? 'Philippines only' : 'See the current service notice' }}.</p>
            <p>Minimum age for account creation and blood donation: {{ config('privacy.minimum_age') }} years old. Accounts for minors are not offered.</p>
            @if(filter_var(config('privacy.contact'), FILTER_VALIDATE_EMAIL))
                <p>Privacy, rights requests, complaints and accessibility assistance: <a href="mailto:{{ config('privacy.contact') }}">{{ config('privacy.contact') }}</a>.</p>
            @else
                <p>The operator must publish a working privacy contact before collecting real personal data.</p>
            @endif
        </section>
    </main>
    <x-privacy-controls />
</body>
</html>
