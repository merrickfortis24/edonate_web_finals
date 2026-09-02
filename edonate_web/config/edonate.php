<?php

return [
    // Keep scheduled jobs opt-in. The command remains available for manual cron setup.
    'schedule_eligibility_reminders' => (bool) env('EDONATE_SCHEDULE_ELIGIBILITY_REMINDERS', false),
    'reminder_time' => env('EDONATE_REMINDER_TIME', '08:00'),

    // Application-action limits. These use Laravel's configured cache store
    // (database by default) and can be overridden per environment without
    // changing route or controller code.
    'rate_limits' => [
        'donor_api_per_minute' => (int) env('EDONATE_RATE_DONOR_API_PER_MINUTE', 60),
        'public_api_per_minute' => (int) env('EDONATE_RATE_PUBLIC_API_PER_MINUTE', 30),
        'donor_login_per_minute' => (int) env('EDONATE_RATE_DONOR_LOGIN_PER_MINUTE', 5),
        'admin_login_per_minute' => (int) env('EDONATE_RATE_ADMIN_LOGIN_PER_MINUTE', 5),
        'login_ip_per_ten_minutes' => (int) env('EDONATE_RATE_LOGIN_IP_PER_TEN_MINUTES', 20),
        'otp_send_per_ten_minutes' => (int) env('EDONATE_RATE_OTP_SEND_PER_TEN_MINUTES', 3),
        'otp_send_ip_per_hour' => (int) env('EDONATE_RATE_OTP_SEND_IP_PER_HOUR', 10),
        'otp_verify_per_ten_minutes' => (int) env('EDONATE_RATE_OTP_VERIFY_PER_TEN_MINUTES', 5),
        'password_reset_per_ten_minutes' => (int) env('EDONATE_RATE_PASSWORD_RESET_PER_TEN_MINUTES', 3),
        'password_reset_ip_per_hour' => (int) env('EDONATE_RATE_PASSWORD_RESET_IP_PER_HOUR', 10),
        'registration_per_hour' => (int) env('EDONATE_RATE_REGISTRATION_PER_HOUR', 5),
        'eligibility_submit_per_ten_minutes' => (int) env('EDONATE_RATE_ELIGIBILITY_SUBMIT_PER_TEN_MINUTES', 10),
        'verification_upload_per_ten_minutes' => (int) env('EDONATE_RATE_VERIFICATION_UPLOAD_PER_TEN_MINUTES', 5),
        'appointment_write_per_minute' => (int) env('EDONATE_RATE_APPOINTMENT_WRITE_PER_MINUTE', 10),
        'blood_request_response_per_minute' => (int) env('EDONATE_RATE_BLOOD_REQUEST_RESPONSE_PER_MINUTE', 10),
        'notification_send_per_minute' => (int) env('EDONATE_RATE_NOTIFICATION_SEND_PER_MINUTE', 10),
        'map_api_per_minute' => (int) env('EDONATE_RATE_MAP_API_PER_MINUTE', 30),
        'reports_api_per_minute' => (int) env('EDONATE_RATE_REPORTS_API_PER_MINUTE', 20),
        'report_export_per_ten_minutes' => (int) env('EDONATE_RATE_REPORT_EXPORT_PER_TEN_MINUTES', 5),
        'admin_api_per_minute' => (int) env('EDONATE_RATE_ADMIN_API_PER_MINUTE', 120),
        'admin_write_per_minute' => (int) env('EDONATE_RATE_ADMIN_WRITE_PER_MINUTE', 30),
        'inventory_update_per_minute' => (int) env('EDONATE_RATE_INVENTORY_UPDATE_PER_MINUTE', 20),
        'document_access_per_minute' => (int) env('EDONATE_RATE_DOCUMENT_ACCESS_PER_MINUTE', 30),
        'admin_2fa_per_ten_minutes' => (int) env('EDONATE_RATE_ADMIN_2FA_PER_TEN_MINUTES', 5),
        'admin_2fa_status_per_minute' => (int) env('EDONATE_RATE_ADMIN_2FA_STATUS_PER_MINUTE', 120),
        'admin_mfa_mobile_per_five_minutes' => (int) env('EDONATE_RATE_ADMIN_MFA_MOBILE_PER_FIVE_MINUTES', 5),
    ],
];
