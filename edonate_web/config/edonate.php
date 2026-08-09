<?php

return [
    // Keep scheduled jobs opt-in. The command remains available for manual cron setup.
    'schedule_eligibility_reminders' => (bool) env('EDONATE_SCHEDULE_ELIGIBILITY_REMINDERS', false),
    'reminder_time' => env('EDONATE_REMINDER_TIME', '08:00'),
];
