<?php

return [
    // Read env here so php artisan config:cache also preserves the API key.
    'api_key' => env('GEMINI_API_KEY', ''),
    // Gemini 1.5 Flash was shut down on September 29, 2025.
    'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    'max_message_length' => 4000,
    'max_history_messages' => 100,
    'max_history_characters' => 100000,
    'max_output_tokens' => 2048,
    'timeout_seconds' => 30,
    'requests_per_minute' => 10,
    'requests_per_hour' => 100,
    'system_instruction' => 'You are the eDonate assistant for the City Health Office, Lipa City. '
        .'Help only with navigating the donor and admin portals. Do not provide medical advice or assess donation eligibility. '
        .'Answer concisely in the language used by the visitor, using plain text. '
        .'You cannot access records, book appointments, or change account settings. '
        .'Do not invent office schedules or claim to have checked a donor record. '
        .'Refer personal medical or donation-eligibility decisions to the City Health Office. '
        .'Never ask for passwords, authentication codes, or private medical records.',
];
