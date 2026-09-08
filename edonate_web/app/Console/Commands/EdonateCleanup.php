<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EdonateCleanup extends Command
{
    protected $signature = 'edonate:cleanup {--dry-run : Report eligible temporary data without deleting anything} {--force : Skip the confirmation prompt}';

    protected $description = 'Safely remove expired temporary eDonate data while preserving history and protected security tables.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->option('force') && ! $this->confirm('Only expired temporary data will be removed. Continue?', false)) {
            $this->info('Cleanup cancelled. No data was changed.');

            return self::SUCCESS;
        }

        $plans = $this->plans();
        $total = 0;

        foreach ($plans as $plan) {
            $count = $this->countPlan($plan);
            $total += $count;
            $prefix = $dryRun ? '[DRY-RUN] ' : '';
            $this->line($prefix . $plan['label'] . ': ' . $count);

            if (! $dryRun && $count > 0) {
                $this->deletePlan($plan);
            }
        }

        $this->handleRejectedVerificationFiles($dryRun);

        $this->line('[SKIPPED] sessions: protected security/system table; no rows changed.');
        $this->line('[SKIPPED] audit_logs, admin_notifications, completed donation history, inventory logs, and fulfilled requests: retained by policy.');

        if ($dryRun) {
            $this->info('Dry run complete. No data was changed.');
        } else {
            $this->info('Cleanup complete. Temporary records removed: ' . $total . '.');
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{key:string,label:string,table:string,column:string,cutoff:int|string,mode:string}> */
    private function plans(): array
    {
        $now = now();

        return [
            [
                'key' => 'chat_messages',
                'label' => 'expired AI chat messages',
                'table' => 'chat_messages',
                'column' => 'created_at',
                'cutoff' => $now->copy()->subDays(max(1, (int) config('retention.chat_message_days', 30))),
                'mode' => 'before_datetime',
            ],
            [
                'key' => 'otp_codes',
                'label' => 'expired OTP codes',
                'table' => 'otp_codes',
                'column' => 'expires_at',
                'cutoff' => $now,
                'mode' => 'before_datetime',
            ],
            [
                'key' => 'donor_forget',
                'label' => 'expired donor reset tokens',
                'table' => 'donor_forget',
                'column' => 'token_expires',
                'cutoff' => $now,
                'mode' => 'before_datetime',
            ],
            [
                'key' => 'password_reset_tokens',
                'label' => 'expired password reset tokens',
                'table' => 'password_reset_tokens',
                'column' => 'created_at',
                'cutoff' => $now->copy()->subHours(24),
                'mode' => 'before_datetime',
            ],
            [
                'key' => 'notifications',
                'label' => 'old read donor notifications',
                'table' => 'notifications',
                'column' => 'created_at',
                'cutoff' => $now->copy()->subDays(max(1, (int) config('retention.read_notification_days', 180))),
                'mode' => 'old_read_notifications',
            ],
        ];
    }

    /** @param array{table:string,column:string,cutoff:int|string,mode:string} $plan */
    private function countPlan(array $plan): int
    {
        if (! Schema::hasTable($plan['table']) || ! Schema::hasColumn($plan['table'], $plan['column'])) {
            return 0;
        }

        try {
            return (int) $this->queryForPlan($plan)->count();
        } catch (Throwable $exception) {
            report($exception);
            $this->warn('Unable to inspect ' . $plan['table'] . '; it was skipped.');

            return 0;
        }
    }

    /** @param array{table:string,column:string,cutoff:int|string,mode:string} $plan */
    private function deletePlan(array $plan): void
    {
        try {
            $this->queryForPlan($plan)->delete();
        } catch (Throwable $exception) {
            report($exception);
            $this->warn('Unable to clean ' . $plan['table'] . '; it was skipped.');
        }
    }

    /** @param array{table:string,column:string,cutoff:int|string,mode:string} $plan */
    private function queryForPlan(array $plan)
    {
        $query = DB::table($plan['table']);

        if ($plan['mode'] === 'old_read_notifications') {
            if (! Schema::hasColumn($plan['table'], 'is_read')) {
                return $query->whereRaw('1 = 0');
            }

            $query->where('is_read', 1);
            // Demo records are retained so the local test dataset stays useful.
            if (Schema::hasColumn($plan['table'], 'message')) {
                $query->where('message', 'not like', '%DEMO%');
            }
        }

        return $query->where($plan['column'], '<', $plan['cutoff']);
    }

    private function handleRejectedVerificationFiles(bool $dryRun): void
    {
        if (! (bool) config('retention.delete_rejected_verification_files', false)) {
            $this->line('[SKIPPED] rejected verification document files: deletion is disabled by default.');

            return;
        }

        if (! Schema::hasTable('donor_verifications')
            || ! Schema::hasColumn('donor_verifications', 'status')
            || ! Schema::hasColumn('donor_verifications', 'document_path')
            || ! Schema::hasColumn('donor_verifications', 'created_at')) {
            return;
        }

        $cutoff = now()->subDays(max(1, (int) config('retention.rejected_verification_document_days', 365)));
        $rows = DB::table('donor_verifications')
            ->where('status', 'rejected')
            ->where('created_at', '<', $cutoff)
            ->get(['document_path']);
        $count = 0;

        foreach ($rows as $row) {
            $path = trim((string) ($row->document_path ?? ''));
            if ($path === '' || ! str_starts_with($path, 'donor-verifications/') || str_contains($path, '..')) {
                continue;
            }
            if (Storage::disk('local')->exists($path)) {
                $count++;
                if (! $dryRun) {
                    Storage::disk('local')->delete($path);
                }
            }
        }

        $this->line(($dryRun ? '[DRY-RUN] ' : '') . 'rejected verification document files: ' . $count);
    }
}
