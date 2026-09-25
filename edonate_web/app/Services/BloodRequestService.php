<?php

namespace App\Services;

use App\Models\BloodRequest;
use App\Models\BloodRequestDonor;
use App\Models\FacilityBloodInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BloodRequestService
{
    public function __construct(
        private readonly DonorMatchingService $matchingService,
        private readonly AdminNotificationService $adminNotificationService
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, Request $httpRequest): BloodRequest
    {
        $adminId = (int) $httpRequest->session()->get('admin_id');

        return DB::transaction(function () use ($data, $httpRequest, $adminId): BloodRequest {
            $payload = $this->requestPayload($data);
            $payload['created_by_admin_id'] = $adminId > 0 ? $adminId : null;
            $payload['status'] = $payload['status'] ?? 'open';

            $request = BloodRequest::query()->create($payload);
            $request->request_reference = $this->referenceFor($request);
            $request->patient_reference_code = $request->patient_reference_code ?: $request->request_reference;
            $request->save();

            $this->audit($httpRequest, 'blood_request_created', 'Created blood request ' . $request->request_reference . '.', $request, [
                'facility_id' => (int) $request->facility_id,
                'blood_type_id' => (int) $request->needed_blood_type_id,
                'required_donors' => $request->requiredDonors(),
                'specific_match_required' => $request->specificMatchesRequired(),
                'urgency' => $request->urgency,
                'new_status' => $request->status,
            ]);

            $this->adminNotificationService->createAdminEvent(
                'blood_request_created',
                'Blood Request Created',
                'Blood request ' . $request->request_reference . ' has been created.',
                'blood_request',
                (int) $request->request_id
            );

            return $request->fresh(['facility', 'bloodType']);
        });
    }

    /** @return array<string, mixed> */
    public function details(BloodRequest $request): array
    {
        $request->loadMissing(['facility', 'bloodType', 'createdBy']);
        $summary = $this->matchingService->summary($request);
        $inventory = FacilityBloodInventory::query()
            ->with('bloodType')
            ->where('facility_id', (int) $request->facility_id)
            ->where('blood_type_id', (int) $request->needed_blood_type_id)
            ->first();

        return [
            'request' => $this->transform($request),
            'summary' => $summary,
            'inventory' => [
                'available_units' => (int) ($inventory?->available_units ?? 0),
                'low_stock_threshold' => (int) ($inventory?->low_stock_threshold ?? config('blood_inventory.default_low_stock_threshold', 5)),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function candidateRows(BloodRequest $request, string $filter = 'recommended'): array
    {
        $attached = BloodRequestDonor::query()
            ->with(['donor.bloodType', 'donor.location'])
            ->where('request_id', $request->request_id);

        if (in_array($filter, ['notified', 'interested', 'declined', 'confirmed', 'completed'], true)) {
            $statuses = match ($filter) {
                'notified' => ['notified', 'contacted'],
                'interested' => ['interested', 'responded'],
                'confirmed' => ['confirmed', 'scheduled'],
                'completed' => ['completed', 'donated'],
                default => [$filter],
            };

            return $attached->whereIn('status', $statuses)->get()->map(fn (BloodRequestDonor $row): array => $this->attachedRow($row))->values()->all();
        }

        $exact = $filter === 'other' ? collect() : $this->matchingService->exactMatches($request, 100);
        $other = $filter === 'exact' ? collect() : $this->matchingService->replacementAnyMatches($request, 100);

        return $exact->merge($other)->map(fn (object $row): array => $this->candidateRow($row))->values()->all();
    }

    /** @param array<int, int> $donorIds */
    public function notifyCandidates(BloodRequest $request, array $donorIds, Request $httpRequest): int
    {
        if (! in_array($request->status, ['open', 'in_progress'], true)) {
            throw ValidationException::withMessages(['request' => 'Only open requests can notify donors.']);
        }

        $eligible = collect($this->candidateRows($request, 'recommended'))->keyBy('donor_id');
        $selected = collect($donorIds)->map(fn (mixed $id): int => (int) $id)->filter()->unique();
        $notified = 0;

        DB::transaction(function () use ($request, $selected, $eligible, &$notified): void {
            foreach ($selected as $donorId) {
                $candidate = $eligible->get($donorId);
                if (! $candidate) {
                    continue;
                }

                $row = BloodRequestDonor::query()->firstOrCreate(
                    ['request_id' => $request->request_id, 'donor_id' => $donorId],
                    ['match_type' => $candidate['match_type'], 'status' => 'candidate']
                );

                if (! in_array($row->status, ['candidate', 'contacted', 'notified'], true)) {
                    continue;
                }

                $alreadyNotified = $row->notified_at !== null || in_array($row->status, ['notified', 'contacted'], true);
                $row->match_type = $candidate['match_type'];
                $row->status = 'notified';
                $row->notified_at = $row->notified_at ?: now();
                $row->save();

                if (! $alreadyNotified) {
                    $this->donorNotification(
                        $donorId,
                        'blood_request_invitation',
                        $this->invitationMessage($request)
                    );
                    $notified++;
                }
            }

            if ($notified > 0 && $request->status === 'open') {
                $request->status = 'in_progress';
                $request->save();
            }
        });

        if ($notified > 0) {
            $this->audit($httpRequest, 'blood_request_candidates_notified', 'Notified candidates for ' . $request->request_reference . '.', $request, [
                'notified_count' => $notified,
            ]);
        }

        return $notified;
    }

    public function respond(BloodRequest $request, int $donorId, string $status): BloodRequestDonor
    {
        if (! in_array($request->status, ['open', 'in_progress'], true)) {
            throw ValidationException::withMessages(['request' => 'This request is no longer accepting responses.']);
        }

        if (! in_array($status, ['interested', 'declined'], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid response.']);
        }

        return DB::transaction(function () use ($request, $donorId, $status): BloodRequestDonor {
            $row = BloodRequestDonor::query()
                ->where('request_id', $request->request_id)
                ->where('donor_id', $donorId)
                ->lockForUpdate()
                ->first();

            if (! $row || ! in_array($row->status, ['notified', 'contacted', 'interested', 'responded', 'declined'], true)) {
                throw ValidationException::withMessages(['request' => 'This request was not sent to your account.']);
            }

            $row->status = $status;
            $row->responded_at = now();
            $row->save();

            return $row;
        });
    }

    public function cancel(BloodRequest $request, string $reason, Request $httpRequest): void
    {
        DB::transaction(function () use ($request, $reason, $httpRequest): void {
            $locked = BloodRequest::query()->whereKey($request->request_id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['open', 'in_progress', 'draft'], true)) {
                throw ValidationException::withMessages(['request' => 'Only active requests can be cancelled.']);
            }

            $locked->status = 'cancelled';
            $locked->cancelled_at = now();
            $locked->cancellation_reason = $reason;
            $locked->save();

            BloodRequestDonor::query()
                ->where('request_id', $locked->request_id)
                ->whereIn('status', ['notified', 'contacted', 'interested', 'responded'])
                ->chunkById(100, function (Collection $rows) use ($locked): void {
                    foreach ($rows as $row) {
                        $this->donorNotification((int) $row->donor_id, 'blood_request_cancelled', 'A blood donation request you were invited to has been cancelled.');
                    }
                });

            $this->audit($httpRequest, 'blood_request_cancelled', 'Cancelled blood request ' . $locked->request_reference . '.', $locked, [
                'reason' => $reason,
                'new_status' => 'cancelled',
            ]);
        });
    }

    public function fulfill(BloodRequest $request, string $note, Request $httpRequest): void
    {
        DB::transaction(function () use ($request, $note, $httpRequest): void {
            $locked = BloodRequest::query()->whereKey($request->request_id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['open', 'in_progress'], true)) {
                throw ValidationException::withMessages(['request' => 'Only active requests can be fulfilled.']);
            }

            $locked->status = 'fulfilled';
            $locked->fulfilled_at = now();
            $locked->fulfillment_note = $note;
            $locked->save();

            $this->audit($httpRequest, 'blood_request_manually_fulfilled', 'Marked blood request ' . $locked->request_reference . ' fulfilled.', $locked, [
                'note' => $note,
                'new_status' => 'fulfilled',
            ]);
        });
    }

    public function updateDonorStatus(BloodRequest $request, int $donorId, string $status, Request $httpRequest): BloodRequestDonor
    {
        if (! in_array($status, ['confirmed', 'completed', 'declined'], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid donor request status.']);
        }

        return DB::transaction(function () use ($request, $donorId, $status, $httpRequest): BloodRequestDonor {
            $row = BloodRequestDonor::query()
                ->where('request_id', $request->request_id)
                ->where('donor_id', $donorId)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                throw ValidationException::withMessages(['donor_id' => 'This donor is not attached to the request.']);
            }

            $previous = (string) $row->status;
            $row->status = $status;
            if (in_array($status, ['declined', 'completed'], true) && $row->responded_at === null) {
                $row->responded_at = now();
            }
            $row->save();

            $lockedRequest = BloodRequest::query()->whereKey($request->request_id)->lockForUpdate()->firstOrFail();
            if ($status === 'completed' && $this->matchingService->isFulfilled($lockedRequest) && $lockedRequest->status !== 'fulfilled') {
                $lockedRequest->status = 'fulfilled';
                $lockedRequest->fulfilled_at = now();
                $lockedRequest->fulfillment_note = 'Automatically fulfilled from completed request donors.';
                $lockedRequest->save();
            }

            $this->audit($httpRequest, 'blood_request_donor_status_updated', 'Updated blood request donor response.', $lockedRequest, [
                'donor_id' => $donorId,
                'previous_status' => $previous,
                'new_status' => $status,
            ]);

            return $row;
        });
    }

    /** @return array<string, mixed> */
    public function transform(BloodRequest $request): array
    {
        return [
            'request_id' => (int) $request->request_id,
            'request_reference' => (string) ($request->request_reference ?: $this->referenceFor($request)),
            'facility_id' => (int) $request->facility_id,
            'facility_name' => (string) ($request->facility?->facility_name ?? 'Unknown facility'),
            'facility_location' => trim(implode(', ', array_filter([
                $request->facility?->barangay_name,
                $request->facility?->city,
                $request->facility?->province,
            ]))),
            'request_type' => (string) ($request->request_type ?? 'replacement_donor'),
            'needed_blood_type_id' => (int) $request->needed_blood_type_id,
            'needed_blood_type' => (string) ($request->bloodType?->blood_type ?? 'Any'),
            'required_donors' => $request->requiredDonors(),
            'specific_match_required' => $request->specificMatchesRequired(),
            'allow_other_blood_types' => $request->allowsOtherBloodTypes(),
            'urgency' => (string) $request->urgency,
            'status' => (string) $request->status,
            'notes' => (string) ($request->notes ?? ''),
            'created_by' => (string) ($request->createdBy?->full_name ?? $request->createdBy?->username ?? 'Admin'),
            'created_at' => $request->created_at?->toIso8601String(),
            'expires_at' => $request->expires_at?->toIso8601String(),
            'fulfilled_at' => $request->fulfilled_at?->toIso8601String(),
            'cancelled_at' => $request->cancelled_at?->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function requestPayload(array $data): array
    {
        $required = max(1, (int) ($data['required_donors'] ?? 1));
        $specific = max(0, (int) ($data['specific_match_required'] ?? 0));
        $allowOther = (bool) ($data['allow_other_blood_types'] ?? false);

        if (! $allowOther) {
            $specific = $required;
        }

        return [
            'facility_id' => (int) $data['facility_id'],
            'request_type' => (string) ($data['request_type'] ?? 'replacement_donor'),
            'needed_blood_type_id' => (int) $data['needed_blood_type_id'],
            'required_donors' => $required,
            'total_donors_needed' => $required,
            'specific_match_required' => $specific,
            'specific_blood_type_required_count' => $specific,
            'allow_other_blood_types' => $allowOther,
            'allow_any_blood_type_replacement' => $allowOther,
            'urgency' => (string) ($data['urgency'] ?? 'normal'),
            'status' => (string) ($data['status'] ?? 'open'),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'expires_at' => $data['expires_at'] ?? null,
        ];
    }

    private function referenceFor(BloodRequest $request): string
    {
        $prefix = ($request->request_type ?? 'replacement_donor') === 'blood_request' ? 'BR' : 'RDR';
        $year = $request->created_at?->format('Y') ?? now()->format('Y');

        return sprintf('%s-%s-%06d', $prefix, $year, (int) $request->request_id);
    }

    private function candidateRow(object $row): array
    {
        return [
            'donor_id' => (int) $row->donor_id,
            'donor_name' => trim((string) $row->first_name . ' ' . (string) $row->last_name),
            'blood_type' => strtolower((string) $row->blood_type_status) === 'verified' ? (string) ($row->blood_type ?? 'Verified') : 'Not verified',
            'blood_type_status' => (string) ($row->blood_type_status ?? 'not_yet_determined'),
            'barangay' => (string) ($row->barangay_name ?? ''),
            'city' => (string) ($row->city ?? ''),
            'eligibility_status' => (string) ($row->eligibility_status ?? 'eligible'),
            'appointment_availability' => 'available',
            'match_type' => (string) $row->match_type,
            'status' => 'candidate',
        ];
    }

    private function attachedRow(BloodRequestDonor $row): array
    {
        $donor = $row->donor;

        return [
            'donor_id' => (int) $row->donor_id,
            'donor_name' => trim((string) $donor?->first_name . ' ' . (string) $donor?->last_name),
            'blood_type' => strtolower((string) $donor?->blood_type_status) === 'verified' ? (string) ($donor?->bloodType?->blood_type ?? 'Verified') : 'Not verified',
            'blood_type_status' => (string) ($donor?->blood_type_status ?? 'not_yet_determined'),
            'barangay' => (string) ($donor?->location?->barangay_name ?? ''),
            'city' => (string) ($donor?->location?->city ?? ''),
            'eligibility_status' => 'eligible',
            'appointment_availability' => 'notified',
            'match_type' => (string) ($row->match_type ?? 'replacement_any'),
            'status' => (string) $row->status,
            'notified_at' => $row->notified_at?->toIso8601String(),
            'responded_at' => $row->responded_at?->toIso8601String(),
        ];
    }

    private function invitationMessage(BloodRequest $request): string
    {
        $request->loadMissing(['facility', 'bloodType']);
        $urgency = Str::headline((string) $request->urgency);

        return "{$urgency} blood donation request at {$request->facility?->facility_name}. Blood type needed: {$request->bloodType?->blood_type}. Please respond through eDonate if you are available and willing to donate.";
    }

    private function donorNotification(int $donorId, string $type, string $message): void
    {
        if ($donorId <= 0 || ! Schema::hasTable('notifications')) {
            return;
        }

        try {
            $payload = [
                'donor_id' => $donorId,
                'message' => $message,
                'notification_type' => $type,
                'is_read' => 0,
                'created_at' => now(),
            ];

            if (Schema::hasColumn('notifications', 'push_sent')) {
                $payload['push_sent'] = 0;
            }

            DB::table('notifications')->insert($payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function audit(Request $request, string $action, string $description, BloodRequest $bloodRequest, array $metadata): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        try {
            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id')) ? (int) $request->session()->get('admin_id') : null,
                'actor_name' => trim((string) ($request->session()->get('admin_full_name') ?: $request->session()->get('admin_username') ?: 'Admin')),
                'actor_role' => ucfirst(Str::lower((string) $request->session()->get('admin_role', 'admin'))),
                'action_type' => $action,
                'module_type' => 'blood_requests',
                'target_table' => 'blood_requests',
                'target_id' => (int) $bloodRequest->request_id,
                'description' => Str::limit($description, 255, ''),
                'ip_address' => $request->ip(),
                'result' => 'success',
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
