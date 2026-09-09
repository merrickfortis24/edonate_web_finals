@extends('layouts.legal')
@section('title', 'Cookie Policy')
@section('policy')
@php($customCookiePolicy = app(\App\Services\PrivacyLegalSettings::class)->policy('cookies'))
@if ($customCookiePolicy !== null)
<section aria-labelledby="published-cookie-policy-heading">
<h2 id="published-cookie-policy-heading">Published Cookie Policy</h2>
@foreach (preg_split('/\n{2,}/u', $customCookiePolicy, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $paragraph)
<p>{!! nl2br(e(trim($paragraph))) !!}</p>
@endforeach
</section>
@else
<section><h2>Cookies and similar storage</h2><p>Browser storage maintains sign-in, protects forms and remembers choices. External requests can also disclose technical information without setting cookies. This policy covers both.</p></section>
<section><h2>Essential storage</h2><table><caption>Storage used to operate eDonate</caption><thead><tr><th scope="col">Item</th><th scope="col">Purpose and duration</th></tr></thead><tbody>
<tr><th scope="row">Laravel session</th><td>Connects this browser to server-side authentication and form state. The configured session lifetime is {{ config('session.lifetime') }} minutes; activity can renew it.</td></tr>
<tr><th scope="row">XSRF-TOKEN</th><td>Protects form requests against cross-site request forgery. It follows the configured session lifetime and is readable by JavaScript for that purpose.</td></tr>
<tr><th scope="row">{{ config('privacy.cookie') }}</th><td>Encrypted, HttpOnly first-party cookie recording your choices for {{ config('privacy.choice_days') }} days. Expiry or a new policy version requires a new choice.</td></tr>
<tr><th scope="row">Remember-me credentials, if selected</th><td>Maintain sign-in according to authentication settings. Log out and clear site data if needed.</td></tr>
<tr><th scope="row">Interface preferences</th><td>Interface components may store a local theme choice until cleared. It is not used for advertising.</td></tr>
</tbody></table></section>
<section><h2>Optional services</h2><p>No analytics or advertising provider is currently enabled. Analytics consent is not offered until a provider has been reviewed and configured.</p><p>Map tiles load only after maps are allowed. OpenStreetMap receives the IP address, browser information and requested tile coordinates. The aggregate table works without map tiles.</p><p>Where enabled for authorized adult staff, AI is blocked until allowed. A conversation identifier is placed in sessionStorage after consent and normally cleared when the tab closes. Messages are stored on the server and forwarded to Gemini. Clearing the identifier does not erase those messages; use the chat delete control or contact the operator. The Privacy Policy explains provider processing.</p><p>Google/Firebase scripts load when Google sign-in is deliberately requested. They are authentication rather than analytics scripts. Google's account flow may use its own authentication storage under its policies.</p></section>
<section><h2>Accept, reject or withdraw</h2><p>Optional services are off by default. Accept, reject and selected-choice controls are equally available. Closing the banner is not consent. Reopen “Privacy choices” in the footer at any time. Withdrawal stops future optional requests and reloads the page to unload running third-party code. It cannot recall a request already sent or erase a provider's existing copy.</p><p>Clear cookies and storage using browser settings if desired; this may sign you out and require a new choice. If JavaScript or storage is blocked, optional services remain off. Essential account processing has its own legal basis and is not bundled with optional consent.</p></section>
<section><h2>Changes</h2><p>The operator must update this inventory before adding another tracker, embed or storage purpose. Privacy rights and contact details are provided below and in the Privacy Policy.</p></section>
@endif
@endsection
