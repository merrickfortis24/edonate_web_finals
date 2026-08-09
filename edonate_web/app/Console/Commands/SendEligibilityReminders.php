<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SendEligibilityReminders extends Command
{
    protected $signature = 'edonate:eligibility-reminders {--dry-run : Preview due donors without creating notifications}';

    protected $description = 'Create one donor notification for donors whose waiting period has ended.';

    public function handle(): int
    {
        if (! Schema::hasTable('eligibility_status') || ! Schema::hasTable('notifications')) {
            $this->warn('Eligibility or notification storage is not available.');

            return self::SUCCESS;
        }

        $requiredEligibilityColumns = ['eligibility_id', 'donor_id', 'status', 'next_eligible_date'];
        foreach ($requiredEligibilityColumns as $column) {
            if (! Schema::hasColumn('eligibility_status', $column)) {
                $this->warn('Missing eligibility_status.' . $column . '; no reminders were sent.');

                return self::SUCCESS;
            }
        }

        $latest = DB::table('eligibility_status')
            ->selectRaw('MAX(eligibility_id) as latest_eligibility_id')
            ->groupBy('donor_id');
        $rows = DB::table('eligibility_status as es')
            ->whereIn('es.eligibility_id', $latest)
            ->whereRaw("LOWER(COALESCE(es.status, '')) = ?", ['temporary_deferred'])
            ->whereNotNull('es.next_eligible_date')
            ->whereDate('es.next_eligible_date', '<=', now()->toDateString())
            ->orderBy('es.donor_id')
            ->get(['es.donor_id', 'es.next_eligible_date']);

        if ($rows->isEmpty()) {
            $this->info('No donors are due for a next-eligible reminder.');

            return self::SUCCESS;
        }

        $created = 0;
        foreach ($rows as $row) {
            $donorId = (int) $row->donor_id;
            $eligibleDate = (string) $row->next_eligible_date;
            $message = 'Your donation waiting period ended on ' . $eligibleDate . '. Please review your eligibility before booking a new appointment.';

            $duplicate = DB::table('notifications')
                ->where('donor_id', $donorId)
                ->where('notification_type', 'next_eligible_reminder')
                ->whereDate('created_at', now()->toDateString())
                ->where('message', $message)
                ->exists();

            if ($duplicate) {
                continue;
            }

            $payload = [
                'donor_id' => $donorId,
                'message' => $message,
                'notification_type' => 'next_eligible_reminder',
                'is_read' => 0,
                'created_at' => now(),
            ];
            if (Schema::hasColumn('notifications', 'push_sent')) {
                $payload['push_sent'] = 0;
            }

            if ($this->option('dry-run')) {
                $this->line('[DRY-RUN] donor #' . $donorId . ' due on ' . $eligibleDate);
                $created++;
                continue;
            }

            try {
                DB::table('notifications')->insert($payload);
                $created++;
            } catch (Throwable $exception) {
                report($exception);
                $this->warn('Unable to create a reminder for donor #' . $donorId . '.');
            }
        }

        $this->info($created . ' next-eligible reminder(s) created. Existing reminders were left unchanged.');

        return self::SUCCESS;
    }
}
