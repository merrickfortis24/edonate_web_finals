<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BloodRequest;
use App\Models\BloodType;
use App\Models\Facility;
use App\Services\BloodRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BloodRequestController extends Controller
{
    public function __construct(private readonly BloodRequestService $service)
    {
    }

    public function index()
    {
        return view('admin.blood_requests', [
            'facilities' => Facility::query()->where('status', 'active')->orderBy('facility_name')->get(),
            'bloodTypes' => BloodType::query()->orderBy('blood_type_id')->get(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(array_merge([''], BloodRequest::STATUSES, ['in_progress']))],
            'urgency' => ['nullable', Rule::in(array_merge([''], BloodRequest::URGENCIES))],
            'facility_id' => ['nullable', 'integer'],
            'blood_type_id' => ['nullable', 'integer'],
        ]);

        $query = BloodRequest::query()
            ->with(['facility', 'bloodType', 'createdBy'])
            ->withCount([
                'donorInvitations AS notified_count' => fn ($builder) => $builder->whereIn('status', ['notified', 'contacted']),
                'donorInvitations AS interested_count' => fn ($builder) => $builder->whereIn('status', ['interested', 'responded']),
                'donorInvitations AS completed_count' => fn ($builder) => $builder->whereIn('status', ['completed', 'donated']),
            ]);

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('request_reference', 'like', $like)
                    ->orWhere('patient_reference_code', 'like', $like)
                    ->orWhereHas('facility', fn ($facility) => $facility->where('facility_name', 'like', $like));
            });
        }

        foreach (['status', 'urgency'] as $filter) {
            if (! empty($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        if (! empty($validated['facility_id'])) {
            $query->where('facility_id', (int) $validated['facility_id']);
        }

        if (! empty($validated['blood_type_id'])) {
            $query->where('needed_blood_type_id', (int) $validated['blood_type_id']);
        }

        $paginator = $query->orderByDesc('created_at')->orderByDesc('request_id')->paginate(
            (int) ($validated['per_page'] ?? 10),
            ['*'],
            'page',
            (int) ($validated['page'] ?? 1)
        );

        $rows = $paginator->getCollection()->map(function (BloodRequest $bloodRequest): array {
            $row = $this->service->transform($bloodRequest);
            $row['notified_count'] = (int) ($bloodRequest->notified_count ?? 0);
            $row['interested_count'] = (int) ($bloodRequest->interested_count ?? 0);
            $row['completed_count'] = (int) ($bloodRequest->completed_count ?? 0);
            $row['show_url'] = route('admin.blood-requests.show', $bloodRequest);
            return $row;
        })->values();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'open' => BloodRequest::query()->whereIn('status', ['open', 'in_progress'])->count(),
                'emergency' => BloodRequest::query()->where('urgency', 'emergency')->whereIn('status', ['open', 'in_progress'])->count(),
                'fulfilled' => BloodRequest::query()->where('status', 'fulfilled')->count(),
                'cancelled' => BloodRequest::query()->where('status', 'cancelled')->count(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedRequest($request);
        $bloodRequest = $this->service->create($validated, $request);

        return response()->json([
            'message' => 'Blood request created.',
            'request' => $this->service->transform($bloodRequest),
        ], 201);
    }

    public function show(BloodRequest $bloodRequest)
    {
        $bloodRequest->loadMissing(['facility', 'bloodType', 'createdBy']);

        return view('admin.blood_request_show', [
            'bloodRequest' => $bloodRequest,
            'details' => $this->service->details($bloodRequest),
        ]);
    }

    public function details(BloodRequest $bloodRequest): JsonResponse
    {
        return response()->json($this->service->details($bloodRequest));
    }

    public function candidates(Request $request, BloodRequest $bloodRequest): JsonResponse
    {
        $validated = $request->validate([
            'filter' => ['nullable', Rule::in(['recommended', 'exact', 'other', 'notified', 'interested', 'declined', 'confirmed', 'completed'])],
        ]);

        return response()->json([
            'data' => $this->service->candidateRows($bloodRequest->loadMissing(['facility', 'bloodType']), $validated['filter'] ?? 'recommended'),
        ]);
    }

    public function notify(Request $request, BloodRequest $bloodRequest): JsonResponse
    {
        $validated = $request->validate([
            'donor_ids' => ['required', 'array', 'min:1', 'max:100'],
            'donor_ids.*' => ['required', 'integer', 'distinct', 'exists:donors,donor_id'],
        ]);

        $count = $this->service->notifyCandidates($bloodRequest->loadMissing(['facility', 'bloodType']), $validated['donor_ids'], $request);

        return response()->json([
            'message' => $count > 0 ? "{$count} candidate(s) notified." : 'No new candidates were notified.',
            'notified_count' => $count,
        ]);
    }

    public function cancel(Request $request, BloodRequest $bloodRequest): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $this->service->cancel($bloodRequest, trim($validated['reason']), $request);

        return response()->json(['message' => 'Blood request cancelled.']);
    }

    public function fulfill(Request $request, BloodRequest $bloodRequest): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $this->service->fulfill($bloodRequest, trim($validated['note']), $request);

        return response()->json(['message' => 'Blood request marked fulfilled.']);
    }

    public function updateDonorStatus(Request $request, BloodRequest $bloodRequest, int $donor): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'completed', 'declined'])],
        ]);

        $row = $this->service->updateDonorStatus($bloodRequest, $donor, $validated['status'], $request);

        return response()->json([
            'message' => 'Donor request status updated.',
            'status' => $row->status,
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedRequest(Request $request): array
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,facility_id'],
            'request_type' => ['required', Rule::in(BloodRequest::REQUEST_TYPES)],
            'needed_blood_type_id' => ['required', 'integer', 'exists:blood_types,blood_type_id'],
            'required_donors' => ['required', 'integer', 'min:1', 'max:1000'],
            'specific_match_required' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'allow_other_blood_types' => ['nullable', 'boolean'],
            'urgency' => ['required', Rule::in(BloodRequest::URGENCIES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $required = (int) $validated['required_donors'];
        $specific = (int) ($validated['specific_match_required'] ?? 0);
        if ($specific > $required) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'specific_match_required' => 'Specific match requirement cannot exceed total required donors.',
            ]);
        }

        $validated['allow_other_blood_types'] = (bool) ($validated['allow_other_blood_types'] ?? false);

        return $validated;
    }
}
