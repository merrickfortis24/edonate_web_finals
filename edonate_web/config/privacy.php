<?php

return [
    // Publication details supplied by the operator. "reviewed" remains a separate
    // release gate for the substantive legal-basis and retention approval.
    'version' => '2026-09-08-v2',
    'reviewed' => (bool) env('PRIVACY_POLICY_REVIEWED', false),
    'controller' => env('PRIVACY_CONTROLLER_NAME', 'City Health Office of Lipa City'),
    'address' => env('PRIVACY_CONTROLLER_ADDRESS', 'City Hall Compound, Lipa City, Batangas, Philippines'),
    'contact' => env('PRIVACY_CONTACT_EMAIL', 'fortismerrick@gmail.com'),
    'representative' => env('PRIVACY_REPRESENTATIVE_NAME', 'John Merrick F. Fortis'),
    'representative_title' => env('PRIVACY_REPRESENTATIVE_TITLE', 'Privacy representative'),
    'philippines_only' => (bool) env('PRIVACY_PHILIPPINES_ONLY', true),
    // Never permit an environment override to weaken the owner-approved floor.
    'minimum_age' => max(18, (int) env('PRIVACY_MINIMUM_AGE', 18)),
    'cookie' => 'edonate_privacy_choices',
    'choice_days' => 180,
    // Browser choices cannot authorize disclosure of other people's records.
    'firebase_sync_enabled' => (bool) env('PRIVACY_FIREBASE_SYNC_ENABLED', false),
    'geocoding_enabled' => (bool) env('PRIVACY_GEOCODING_ENABLED', false),
    // Gemini's age/region/paid-service restrictions require an operator review.
    'ai_enabled' => (bool) env('PRIVACY_AI_ENABLED', false),
    // No analytics provider is currently installed. Do not offer a fake opt-in.
    'analytics_enabled' => false,
    'data_forms' => [
        'donor.signup.store' => 'registration',
        'donor.signup.send-otp' => 'registration',
        'donor.profile.complete' => 'donor-profile',
        'donor.check-eligibility.submit' => 'health-screening',
        'donor.verification.store' => 'identity-verification',
        'donor.book-appointment.store' => 'appointment',
        'donor.blood-requests.interested' => 'blood-request',
    ],
    'purposes' => [
        'registration' => 'I explicitly consent to processing my age, blood type and donor details to create my donor profile and assess donation eligibility.',
        'google-account' => 'I request Google sign-in, confirm that I am at least 18 years old when creating an account, and consent to eDonate receiving my verified email and account identity from Google to create or access my donor account.',
        'donor-profile' => 'I explicitly consent to processing these identity, contact, age and blood-type details to maintain my donor profile.',
        'health-screening' => 'I explicitly consent to authorized donation staff processing these health answers to assess my eligibility. I may ask for a human review of the result.',
        'identity-verification' => 'I explicitly consent to authorized reviewers using this identity document to verify my donor identity. I have removed unrelated information where possible.',
        'appointment' => 'I request this appointment and acknowledge that authorized donation staff will use my donor details to arrange it.',
        'blood-request' => 'I explicitly consent to authorized donation staff using my contact and donor details to coordinate my response to this blood request.',
    ],
];
