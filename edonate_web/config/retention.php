<?php

return [
    'chat_message_days' => (int) env('EDONATE_CHAT_RETENTION_DAYS', 30),
    // Expired one-time codes can be removed because they cannot authenticate a user.
    'otp_expired' => true,
    'donor_reset_expired' => true,

    // Read donor notifications are temporary inbox items, not donation history.
    'read_notification_days' => (int) env('EDONATE_READ_NOTIFICATION_RETENTION_DAYS', 180),

    // Rejected document files may be removed after this period. Rows stay as review history.
    'rejected_verification_document_days' => (int) env('EDONATE_REJECTED_DOCUMENT_RETENTION_DAYS', 365),
    'delete_rejected_verification_files' => (bool) env('EDONATE_DELETE_REJECTED_DOCUMENT_FILES', false),

    // sessions is intentionally reported but never changed by this command because it is a
    // protected security/system table in this installation.
    'report_stale_sessions' => true,
    'stale_session_days' => (int) env('EDONATE_STALE_SESSION_DAYS', 30),
];
