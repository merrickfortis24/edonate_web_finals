<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AnonymizeUnderageDonors extends Command
{
    protected $signature = 'privacy:anonymize-underage-donors
                            {--confirm : Apply the production anonymization instead of a dry run}';

    protected $description = 'Anonymize donor records that belong to users below the configured minimum age.';

    public function handle(): int
    {
        if (! app()->environment('production')) {
            $this->error('This command is restricted to the production environment.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('donors') || ! Schema::hasColumn('donors', 'birthdate')) {
            $this->error('The donors.birthdate field is not available; no cleanup was performed.');

            return self::FAILURE;
        }

        $donorIds = $this->underageDonorIds();
        $this->line('Underage donor records found: '.count($donorIds));

        if (! $this->option('confirm')) {
            $this->warn('Dry run only. Re-run with --confirm to apply the anonymization.');

            return self::SUCCESS;
        }

        if ($donorIds === []) {
            $this->info('No underage donor records require anonymization.');

            return self::SUCCESS;
        }

        $transactionCommitted = false;

        try {
            $result = DB::transaction(function () use ($donorIds): array {
                $documentPaths = $this->anonymizeVerificationRecords($donorIds);

                return [
                    'donors' => $this->anonymizeDonors($donorIds),
                    'auth' => $this->anonymizeAuthentication($donorIds),
                    'verifications' => count($documentPaths),
                    'document_paths' => $documentPaths,
                    'notifications' => $this->deleteLinkedRows('notifications', 'donor_id', $donorIds),
                    'request_matches' => $this->deleteLinkedRows('blood_request_donors', 'donor_id', $donorIds),
                ];
            });
            $transactionCommitted = true;

            $deletedFiles = 0;
            $fileDeletionFailures = 0;
            foreach ($result['document_paths'] as $path) {
                if ($path === '') {
                    continue;
                }

                try {
                    $deleted = Storage::disk('local')->delete($path);
                    if ($deleted || ! Storage::disk('local')->exists($path)) {
                        $deletedFiles++;
                    } else {
                        $fileDeletionFailures++;
                    }
                } catch (Throwable) {
                    $fileDeletionFailures++;
                }
            }

            if ($fileDeletionFailures !== 0) {
                $this->error("Cleanup completed in the database, but {$fileDeletionFailures} verification file(s) could not be removed.");

                return self::FAILURE;
            }

            $remaining = count($this->underageDonorIds());
            if ($remaining !== 0) {
                $this->error("Cleanup verification failed: {$remaining} underage donor record(s) remain.");

                return self::FAILURE;
            }

            $this->info('Underage donor anonymization completed and verified.');
            $this->line('Donor records anonymized: '.$result['donors']);
            $this->line('Authentication records anonymized: '.$result['auth']);
            $this->line('Verification records redacted: '.$result['verifications']);
            $this->line('Verification files removed: '.$deletedFiles);
            $this->line('Donor notifications removed: '.$result['notifications']);
            $this->line('Blood-request matches removed: '.$result['request_matches']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($transactionCommitted
                ? 'Underage donor anonymization committed, but final verification failed; inspect the deployment log before retrying.'
                : 'Underage donor anonymization failed; the database transaction was rolled back.');

            return self::FAILURE;
        }
    }

    /**
     * Return only donor IDs so no personal data is printed to deployment logs.
     * The same predicate is used by privacy:check.
     *
     * @return list<int>
     */
    private function underageDonorIds(): array
    {
        $cutoff = now()->subYears((int) config('privacy.minimum_age', 18))->toDateString();

        return DB::table('donors')
            ->whereNotNull('birthdate')
            ->whereDate('birthdate', '>', $cutoff)
            ->orderBy('donor_id')
            ->pluck('donor_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Remove direct identity data while retaining the donor row as a
     * referentially-safe, inactive audit placeholder.
     *
     * @param list<int> $donorIds
     */
    private function anonymizeDonors(array $donorIds): int
    {
        $columns = Schema::getColumnListing('donors');

        foreach ($donorIds as $donorId) {
            $updates = [];

            if (in_array('first_name', $columns, true)) {
                $updates['first_name'] = 'Redacted';
            }
            if (in_array('last_name', $columns, true)) {
                $updates['last_name'] = 'Donor';
            }
            if (in_array('gender', $columns, true) && $this->nullable('donors', 'gender')) {
                $updates['gender'] = null;
            }
            if (in_array('birthdate', $columns, true)) {
                $updates['birthdate'] = $this->nullable('donors', 'birthdate') ? null : '1900-01-01';
            }
            if (in_array('contact_number', $columns, true)) {
                $updates['contact_number'] = $this->nullable('donors', 'contact_number')
                    ? null
                    : 'redacted-'.$donorId;
            }
            if (in_array('blood_type_id', $columns, true) && $this->nullable('donors', 'blood_type_id')) {
                $updates['blood_type_id'] = null;
            }
            if (in_array('location_id', $columns, true) && $this->nullable('donors', 'location_id')) {
                $updates['location_id'] = null;
            }
            if (in_array('date_registered', $columns, true) && $this->nullable('donors', 'date_registered')) {
                $updates['date_registered'] = null;
            }
            if (in_array('verification_status', $columns, true)) {
                $updates['verification_status'] = 'rejected';
            }
            if (in_array('is_active', $columns, true)) {
                $updates['is_active'] = false;
            }
            if (in_array('blood_type_status', $columns, true)) {
                $updates['blood_type_status'] = 'not_yet_determined';
            }
            if (in_array('blood_type_verified_by_admin_id', $columns, true)
                && $this->nullable('donors', 'blood_type_verified_by_admin_id')) {
                $updates['blood_type_verified_by_admin_id'] = null;
            }
            if (in_array('blood_type_verified_at', $columns, true)
                && $this->nullable('donors', 'blood_type_verified_at')) {
                $updates['blood_type_verified_at'] = null;
            }

            DB::table('donors')->where('donor_id', $donorId)->update($updates);
        }

        return count($donorIds);
    }

    /**
     * Invalidate donor login credentials and replace email identifiers with
     * unique non-routable values.
     *
     * @param list<int> $donorIds
     */
    private function anonymizeAuthentication(array $donorIds): int
    {
        if (! Schema::hasTable('donor_authentication')) {
            return 0;
        }

        $columns = Schema::getColumnListing('donor_authentication');
        $rows = DB::table('donor_authentication')
            ->whereIn('donor_id', $donorIds)
            ->get(['auth_id']);

        foreach ($rows as $row) {
            $updates = [];
            $authId = (int) $row->auth_id;

            if (in_array('email', $columns, true)) {
                $updates['email'] = "redacted-underage-{$authId}@invalid.local";
            }
            if (in_array('password', $columns, true)) {
                $updates['password'] = Hash::make(Str::random(128));
            }
            if (in_array('is_verified', $columns, true)) {
                $updates['is_verified'] = false;
            }
            foreach (['verification_token', 'verification_sent_at', 'verified_at'] as $column) {
                if (in_array($column, $columns, true) && $this->nullable('donor_authentication', $column)) {
                    $updates[$column] = null;
                }
            }

            DB::table('donor_authentication')->where('auth_id', $authId)->update($updates);
        }

        return $rows->count();
    }

    /**
     * Redact document metadata and return paths for deletion after commit.
     * Files are removed only after the database transaction succeeds.
     *
     * @param list<int> $donorIds
     * @return list<string>
     */
    private function anonymizeVerificationRecords(array $donorIds): array
    {
        if (! Schema::hasTable('donor_verifications')) {
            return [];
        }

        $columns = Schema::getColumnListing('donor_verifications');
        $select = ['verification_id'];
        if (in_array('document_path', $columns, true)) {
            $select[] = 'document_path';
        }
        $rows = DB::table('donor_verifications')
            ->whereIn('donor_id', $donorIds)
            ->get($select);
        $paths = [];

        foreach ($rows as $row) {
            $updates = [];
            if (isset($row->document_path) && is_string($row->document_path)) {
                $paths[] = $row->document_path;
            }
            if (in_array('document_path', $columns, true)) {
                $updates['document_path'] = '';
            }
            if (in_array('status', $columns, true)) {
                $updates['status'] = 'rejected';
            }
            if (in_array('rejection_reason', $columns, true)) {
                $updates['rejection_reason'] = 'Redacted under the privacy retention procedure.';
            }
            foreach (['reviewed_by_admin_id', 'reviewed_at'] as $column) {
                if (in_array($column, $columns, true) && $this->nullable('donor_verifications', $column)) {
                    $updates[$column] = null;
                }
            }

            DB::table('donor_verifications')
                ->where('verification_id', (int) $row->verification_id)
                ->update($updates);
        }

        return array_values(array_unique($paths));
    }

    /**
     * Delete personal notification/request-match rows that cannot be useful
     * after a donor has been anonymized.
     *
     * @param list<int> $donorIds
     */
    private function deleteLinkedRows(string $table, string $column, array $donorIds): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $donorIds)->delete();
    }

    private function nullable(string $table, string $column): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return true;
        }

        $metadata = DB::selectOne(
            'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );

        return $metadata !== null && strtoupper((string) ($metadata->IS_NULLABLE ?? 'NO')) === 'YES';
    }
}
