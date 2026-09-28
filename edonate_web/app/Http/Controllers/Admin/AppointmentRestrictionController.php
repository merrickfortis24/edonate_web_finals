<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppointmentRestrictionAppeal;
use App\Models\Donor;
use App\Services\AppointmentRestrictionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentRestrictionController extends Controller
{
    public function index(Request $request, AppointmentRestrictionService $service)
    {
        $this->authorizeAdministrator($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $latestAuth = DB::table('donor_authentication as da')
            ->select('da.donor_id', DB::raw('MAX(da.auth_id) as latest_auth_id'))
            ->groupBy('da.donor_id');
        $latestCancellation = DB::table('appointment_cancellations as ac')
            ->select('ac.donor_id', DB::raw('MAX(ac.cancellation_id) as latest_cancellation_id'))
            ->groupBy('ac.donor_id');
        $pendingAppeals = DB::table('appointment_restrictions as ar')
            ->join('appointment_restriction_appeals as ara', 'ara.restriction_id', '=', 'ar.restriction_id')
            ->where('ar.status', 'active')
            ->where('ara.status', 'pending')
            ->select('ar.donor_id')
            ->groupBy('ar.donor_id');

        $query = DB::table('donors as d')
            ->leftJoinSub($latestAuth, 'latest_auth', function ($join): void {
                $join->on('latest_auth.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('donor_authentication as da', 'da.auth_id', '=', 'latest_auth.latest_auth_id')
            ->leftJoinSub($latestCancellation, 'latest_cancellation', function ($join): void {
                $join->on('latest_cancellation.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('appointment_cancellations as ac', 'ac.cancellation_id', '=', 'latest_cancellation.latest_cancellation_id')
            ->leftJoinSub($pendingAppeals, 'pending_appeals', function ($join): void {
                $join->on('pending_appeals.donor_id', '=', 'd.donor_id');
            })
            ->where('d.appointment_restricted', true)
            ->select([
                'd.donor_id',
                'd.first_name',
                'd.last_name',
                'd.contact_number',
                'd.consecutive_cancellations',
                'd.restriction_status',
                'd.restricted_at',
                'da.email',
                'ac.cancelled_at as latest_cancellation_at',
                'ac.appointment_id as latest_cancelled_appointment_id',
                'pending_appeals.donor_id as has_pending_appeal',
            ]);

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('d.first_name', 'like', '%'.$search.'%')
                    ->orWhere('d.last_name', 'like', '%'.$search.'%')
                    ->orWhere('da.email', 'like', '%'.$search.'%')
                    ->orWhere('d.contact_number', 'like', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $builder->orWhere('d.donor_id', (int) $search);
                }
            });
        }

        return view('admin.appointment_restrictions.index', [
            'donors' => $query->orderByDesc('d.restricted_at')->paginate(15)->withQueryString(),
            'search' => $search,
            'stats' => $service->dashboardCounts(),
        ]);
    }

    public function show(Request $request, int $donor)
    {
        $this->authorizeAdministrator($request);

        $record = Donor::query()->findOrFail($donor);
        $record->setAttribute(
            'email',
            DB::table('donor_authentication')->where('donor_id', $donor)->orderByDesc('auth_id')->value('email')
        );

        $cancellations = $record->appointmentCancellations()
            ->with(['appointment.event'])
            ->orderByDesc('cancelled_at')
            ->paginate(20, ['*'], 'cancellations_page');
        $restrictions = $record->appointmentRestrictions()
            ->with(['appeals.reviews.admin', 'reviews.admin'])
            ->orderByDesc('restricted_at')
            ->get();

        return view('admin.appointment_restrictions.show', [
            'donor' => $record,
            'cancellations' => $cancellations,
            'restrictions' => $restrictions,
            'activeRestriction' => $restrictions->firstWhere('status', 'active'),
        ]);
    }

    public function approveAppeal(Request $request, int $appeal, AppointmentRestrictionService $service): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $record = AppointmentRestrictionAppeal::query()->findOrFail($appeal);
        $service->reviewAppeal($appeal, $this->adminId($request), 'approve', $validated['admin_notes'] ?? null, $request);

        return redirect()->route('admin.appointment-restrictions.show', $record->donor_id)->with('success', 'Appeal approved and appointment privileges restored.');
    }

    public function rejectAppeal(Request $request, int $appeal, AppointmentRestrictionService $service): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        $record = AppointmentRestrictionAppeal::query()->findOrFail($appeal);
        $service->reviewAppeal($appeal, $this->adminId($request), 'reject', $validated['admin_notes'], $request);

        return redirect()->route('admin.appointment-restrictions.show', $record->donor_id)->with('success', 'Appeal rejected. The appointment restriction remains active.');
    }

    public function lift(Request $request, int $donor, AppointmentRestrictionService $service): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        $record = Donor::query()->findOrFail($donor);
        $service->liftManually($record, $this->adminId($request), $validated['admin_notes'], $request);

        return redirect()->route('admin.appointment-restrictions.show', $donor)->with('success', 'Appointment restriction lifted. The donor may book appointments again.');
    }

    public function keepRestricted(Request $request, int $donor, AppointmentRestrictionService $service): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        $record = Donor::query()->findOrFail($donor);
        $service->keepRestricted($record, $this->adminId($request), $validated['admin_notes'], $request);

        return redirect()->route('admin.appointment-restrictions.show', $donor)->with('success', 'The restriction remains active and the review was recorded.');
    }

    private function authorizeAdministrator(Request $request): void
    {
        if (strtolower(trim((string) $request->session()->get('admin_role'))) !== 'admin') {
            abort(403, 'Only administrators may review appointment restrictions.');
        }
    }

    private function adminId(Request $request): int
    {
        $adminId = (int) $request->session()->get('admin_id');
        if ($adminId <= 0) {
            throw ValidationException::withMessages(['admin' => 'Your administrator session has expired. Please sign in again.']);
        }

        return $adminId;
    }
}
