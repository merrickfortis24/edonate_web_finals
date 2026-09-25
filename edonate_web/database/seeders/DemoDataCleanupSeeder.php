<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Deletes only rows carrying the DemoDataSeeder markers. */
class DemoDataCleanupSeeder extends Seeder
{
    /** @var array<int, string> */
    private const PROTECTED_TABLES = [
        'admins',
        'admin_notifications',
        'admin_security_settings',
        'audit_logs',
        'sessions',
        'migrations',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoDataCleanupSeeder is restricted to the local or testing environment.');
        }

        $protectedBefore = $this->protectedSnapshot();
        $placeholderPaths = DB::table('donor_verifications')
            ->where(function ($query): void {
                $query->where('document_path', 'like', 'donor-verifications/%/demo-id-%')
                    ->orWhere('document_path', 'like', 'demo/verifications/demo-id-%');
            })
            ->pluck('document_path')
            ->map(fn ($path): string => (string) $path)
            ->all();

        $counts = DB::transaction(function () use ($protectedBefore, $placeholderPaths): array {
            $donorIds = DB::table('donor_authentication')
                ->where('email', 'like', 'demo.donor%@example.test')
                ->pluck('donor_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $facilityIds = DB::table('facilities')
                ->whereIn('facility_name', $this->facilityNames())
                ->pluck('facility_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $eventIds = DB::table('donation_events')
                ->whereIn('title', $this->eventTitles())
                ->pluck('event_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $requestIds = DB::table('blood_requests')
                ->where('request_reference', 'like', 'DEMO-BR-%')
                ->pluck('request_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $appointmentIds = DB::table('appointments')
                ->whereIn('event_id', $eventIds)
                ->where('donation_center', 'like', 'DEMO seed:%')
                ->pluck('appointment_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $eligibilityIds = DB::table('eligibility_status')
                ->when($donorIds !== [], fn ($query) => $query->whereIn('donor_id', $donorIds))
                ->where('review_notes', 'like', 'DEMO seed%')
                ->pluck('eligibility_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $submissionIds = DB::table('eligibility_submissions')
                ->when($donorIds !== [], fn ($query) => $query->whereIn('donor_id', $donorIds))
                ->where('source', 'demo_seed_v1')
                ->pluck('submission_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $counts = [];
            $counts['notifications'] = $this->deleteWhereIn('notifications', 'donor_id', $donorIds);
            $counts['blood_request_donors'] = $this->deleteWhereIn('blood_request_donors', 'request_id', $requestIds);
            $counts['blood_requests'] = DB::table('blood_requests')->where('request_reference', 'like', 'DEMO-BR-%')->delete();
            $counts['donation_records'] = $this->deleteWhereIn('donation_records', 'appointment_id', $appointmentIds);
            $counts['appointments'] = $this->deleteWhereIn('appointments', 'appointment_id', $appointmentIds);
            $counts['donor_screening_answers'] = DB::table('donor_screening_answers')
                ->when($eligibilityIds !== [], fn ($query) => $query->whereIn('eligibility_id', $eligibilityIds))
                ->where('followup_answer', 'like', 'DEMO%')
                ->delete();
            $counts['eligibility_answers'] = $this->deleteWhereIn('eligibility_answers', 'submission_id', $submissionIds);
            $counts['eligibility_submissions'] = $this->deleteWhereIn('eligibility_submissions', 'submission_id', $submissionIds);
            $counts['eligibility_status'] = $this->deleteWhereIn('eligibility_status', 'eligibility_id', $eligibilityIds);
            $counts['donor_verifications'] = DB::table('donor_verifications')
                ->whereIn('document_path', $placeholderPaths)
                ->delete();
            $counts['donor_authentication'] = DB::table('donor_authentication')
                ->where('email', 'like', 'demo.donor%@example.test')
                ->delete();
            $counts['donors'] = $this->deleteWhereIn('donors', 'donor_id', $donorIds);
            $counts['facility_blood_inventory_logs'] = $this->deleteWhereIn('facility_blood_inventory_logs', 'facility_id', $facilityIds);
            $counts['facility_blood_inventory'] = $this->deleteWhereIn('facility_blood_inventory', 'facility_id', $facilityIds);
            $counts['facilities'] = $this->deleteWhereIn('facilities', 'facility_id', $facilityIds);
            $counts['donation_events'] = $this->deleteWhereIn('donation_events', 'event_id', $eventIds);
            $counts['screening_questions'] = DB::table('screening_questions')->where('question_text', 'like', 'DEMO - %')->delete();
            $counts['eligibility_questions'] = DB::table('eligibility_questions')->where('question_text', 'like', 'DEMO - %')->delete();
            $counts['locations'] = DB::table('locations')->where('street_address', 'like', 'DEMO Seed Road %')->delete();

            $this->assertProtectedSnapshot($protectedBefore);

            return $counts;
        });

        $this->deletePlaceholderFiles($placeholderPaths);
        $this->assertProtectedSnapshot($protectedBefore);

        if ($this->command) {
            $this->command->info('DEMO data cleanup completed.');
            foreach ($counts as $table => $count) {
                $this->command->line(sprintf('%-34s %d removed', $table, $count));
            }
            $this->command->line('Blood types and all protected admin/security/system tables were intentionally left untouched.');
        }
    }

    /** @param array<int, int> $ids */
    private function deleteWhereIn(string $table, string $column, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int) DB::table($table)->whereIn($column, $ids)->delete();
    }

    /** @return array<int, string> */
    private function facilityNames(): array
    {
        return [
            'DEMO Lipa Central Medical Center',
            'DEMO City Blood Bank',
            'DEMO Community Health Center',
            'DEMO South Lipa Clinic',
            'DEMO North Lipa Hospital Annex',
            'DEMO Barangay Wellness Clinic',
            'DEMO Mobile Blood Collection Hub',
            'DEMO Riverside Health Center',
            'DEMO Training Clinic',
            'DEMO Reserve Blood Storage',
        ];
    }

    /** @return array<int, string> */
    private function eventTitles(): array
    {
        return [
            'DEMO Event 01 - Summer Blood Drive',
            'DEMO Event 02 - Community Donor Day',
            'DEMO Event 03 - Barangay Outreach',
            'DEMO Event 04 - Health Week Collection',
            'DEMO Event 05 - South Lipa Drive',
            'DEMO Event 06 - Today Check-in Simulation',
            'DEMO Event 07 - Open Community Drive',
            'DEMO Event 08 - Nearly Full Donor Day',
            'DEMO Event 09 - Full Capacity Test Event',
            'DEMO Event 10 - Nearly Full Health Camp',
            'DEMO Event 11 - Closed Future Schedule',
            'DEMO Event 12 - Cancelled Future Drive',
            'DEMO Event 13 - Reschedule Workflow Event',
            'DEMO Event 14 - Large Availability Event',
        ];
    }

    /** @return array<string, mixed> */
    private function protectedSnapshot(): array
    {
        $snapshot = [];
        foreach (self::PROTECTED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $snapshot[$table] = ['missing' => true];
                continue;
            }

            $rows = DB::table($table)->get()
                ->map(fn ($row): string => json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '')
                ->sort()
                ->values()
                ->all();
            $snapshot[$table] = [
                'count' => count($rows),
                'hash' => hash('sha256', implode("\n", $rows)),
            ];
        }

        return $snapshot;
    }

    /** @param array<string, mixed> $before */
    private function assertProtectedSnapshot(array $before): void
    {
        if ($before !== $this->protectedSnapshot()) {
            throw new RuntimeException('Protected admin/security/system tables changed during demo cleanup. Transaction aborted.');
        }
    }

    /** @param array<int, string> $paths */
    private function deletePlaceholderFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (str_contains($path, '..') || (! str_starts_with($path, 'donor-verifications/') && ! str_starts_with($path, 'demo/verifications/'))) {
                continue;
            }

            Storage::disk('local')->delete($path);
        }
    }
}
