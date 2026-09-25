@once
@php($enforceCookieConsentBanner = app(\App\Services\PrivacyLegalSettings::class)->enforceCookieConsentBanner())
<footer class="ed-legal-footer">
    <nav aria-label="Legal information">
        <a href="{{ route('privacy') }}">Privacy Policy</a>
        <a href="{{ route('terms') }}">Terms and Conditions</a>
        <a href="{{ route('cookies') }}">Cookie Policy</a>
        <button type="button" data-privacy-open aria-controls="ed-privacy-panel" aria-expanded="false">Privacy choices</button>
    </nav>
</footer>
<section id="ed-privacy-panel" class="ed-privacy-panel" aria-labelledby="ed-privacy-title" hidden
         data-endpoint="{{ route('privacy.preferences', [], false) }}"
         data-version="{{ config('privacy.version') }}"
         data-enforce-banner="{{ $enforceCookieConsentBanner ? 'true' : 'false' }}">
    <h2 id="ed-privacy-title" tabindex="-1">Your privacy choices</h2>
    <p>Essential cookies keep sign-in and security working. Optional services stay off until you choose them. Rejecting them does not prevent you from using your account.</p>
    <form id="ed-privacy-form">
        <fieldset>
            <legend>Optional services</legend>
            <label><input type="checkbox" name="maps"> External maps — OpenStreetMap receives your IP address and requested map area.</label>
            @if(config('privacy.ai_enabled') && (int) session('admin_id') > 0)
                <label><input type="checkbox" name="ai"> AI assistant — messages are saved by eDonate and sent to Google Gemini. Do not send anyone's personal or medical information.</label>
            @endif
            @if(config('privacy.analytics_enabled'))
                <label><input type="checkbox" name="analytics"> Usage analytics (optional).</label>
            @else
                <p>No analytics or advertising trackers are enabled.</p>
            @endif
        </fieldset>
        <p><a href="{{ route('cookies') }}">Read the Cookie Policy</a>. Change or withdraw your choices here at any time.</p>
        <p id="ed-privacy-status" role="status" aria-live="polite"></p>
        <div class="ed-privacy-actions">
            <button type="button" data-privacy-reject>Reject optional services</button>
            <button type="submit">Save selected choices</button>
            <button type="button" data-privacy-accept>Accept optional services</button>
            <button type="button" data-privacy-close>Close</button>
        </div>
    </form>
</section>
<script id="ed-privacy-state" type="application/json">{!! json_encode(app(\App\Services\PrivacyConsent::class)->choices(request()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<noscript><p class="ed-legal-footer">Optional external services are disabled because JavaScript is unavailable. Essential account functions still use security cookies.</p></noscript>
@endonce
