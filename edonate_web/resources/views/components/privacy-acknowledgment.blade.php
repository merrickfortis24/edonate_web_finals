@props(['purpose'])
@php($prefix = 'privacy-'.$purpose)
<fieldset class="ed-data-consent">
    <legend>Before you submit</legend>
    <input type="hidden" name="privacy_version" value="{{ config('privacy.version') }}">
    <label for="{{ $prefix }}-notice">
        <input id="{{ $prefix }}-notice" type="checkbox" name="privacy_acknowledged" value="1" required aria-describedby="{{ $prefix }}-error">
        I agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener noreferrer">Terms and Conditions (opens a new tab)</a>
        and acknowledge the <a href="{{ route('privacy') }}" target="_blank" rel="noopener noreferrer">Privacy Policy (opens a new tab)</a>.
    </label>
    <label for="{{ $prefix }}-purpose">
        <input id="{{ $prefix }}-purpose" type="checkbox" name="purpose_accepted" value="1" required aria-describedby="{{ $prefix }}-error">
        {{ config('privacy.purposes.'.$purpose) }}
    </label>
    <p>Optional tracking and AI choices are separate. For alternatives or withdrawal, use the privacy contact in the policy. Declining this processing prevents this particular submission.</p>
    <p id="{{ $prefix }}-error" class="ed-consent-error" role="alert">{{ $errors->first('privacy_acknowledged') ?: ($errors->first('purpose_accepted') ?: $errors->first('privacy_version')) }}</p>
</fieldset>
