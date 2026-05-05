@component('layouts.donor-portal', [
    'pageTitle' => 'Identity Verification',
    'pageHeading' => 'Identity Verification',
    'pageSubheading' => 'Submit a valid ID or document so authorized staff can verify your donor account.',
    'navLinks' => $navLinks,
    'activeNav' => $activeNav,
    'user' => $user,
    'totalDonations' => $totalDonations,
])
@php
    $status = $verificationStatus ?? 'unverified';
    $statusLabel = match ($status) {
        'pending' => 'Pending Verification',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        default => 'Unverified',
    };
    $statusClass = match ($status) {
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'verified' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'rejected' => 'bg-red-50 text-red-700 ring-red-200',
        default => 'bg-slate-100 text-slate-700 ring-slate-200',
    };
    $latestEligibilityStatus = strtolower((string) ($latestEligibility?->status ?? 'none'));
@endphp

<div class="mx-auto grid w-full max-w-5xl gap-6 lg:grid-cols-3">
    <section class="lg:col-span-2">
        <x-dashboard.card title="Submit Verification Document" subtitle="Accepted files: JPG, PNG, or PDF up to 5MB.">
            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold">Please correct the following:</p>
                    <ul class="mt-1 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $hasPassedEligibility)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Please pass the eligibility screening before submitting identity verification.
                    <a href="{{ route('donor.check-eligibility') }}" class="font-semibold underline">Check eligibility</a>
                </div>
            @elseif ($status === 'pending')
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Your document is pending admin review. You will be notified after a decision is made.
                </div>
            @elseif ($status === 'verified')
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    Your identity is verified. You can proceed with appointment booking.
                </div>
            @else
                @if ($status === 'rejected' && $latestVerification?->rejection_reason)
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <p class="font-semibold">Previous submission was rejected.</p>
                        <p class="mt-1">{{ $latestVerification->rejection_reason }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('donor.verification.store') }}" enctype="multipart/form-data" class="grid gap-4">
                    @csrf

                    <div>
                        <label for="document_type" class="mb-1 block text-sm font-semibold text-slate-700">Document Type</label>
                        <select id="document_type" name="document_type" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" required>
                            <option value="" disabled {{ old('document_type') ? '' : 'selected' }}>Select document type</option>
                            @foreach ($documentTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('document_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="document" class="mb-1 block text-sm font-semibold text-slate-700">Upload Document</label>
                        <input id="document" name="document" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-red-700 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" required>
                        <p class="mt-2 text-xs text-slate-500">Use a clear photo or scan. Sensitive documents are stored privately and reviewed only by authorized admins.</p>
                    </div>

                    <button type="submit" class="rounded-xl bg-red-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-red-800">
                        Submit for Verification
                    </button>
                </form>
            @endif
        </x-dashboard.card>
    </section>

    <aside class="space-y-6">
        <x-dashboard.card title="Verification Status" subtitle="Current identity review result.">
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">{{ $statusLabel }}</span>
            <div class="mt-4 space-y-2 text-sm text-slate-600">
                <p><span class="font-semibold text-slate-900">Eligibility:</span> {{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $latestEligibilityStatus)) }}</p>
                <p><span class="font-semibold text-slate-900">Latest submission:</span> {{ $latestVerification?->created_at ? \Carbon\Carbon::parse($latestVerification->created_at)->format('M j, Y g:i A') : '-' }}</p>
                <p><span class="font-semibold text-slate-900">Reviewed at:</span> {{ $latestVerification?->reviewed_at ? \Carbon\Carbon::parse($latestVerification->reviewed_at)->format('M j, Y g:i A') : '-' }}</p>
            </div>
        </x-dashboard.card>

        <x-dashboard.card title="Verification History" subtitle="Recent document submissions.">
            @if ($verificationHistory->isEmpty())
                <p class="text-sm text-slate-600">No verification submissions yet.</p>
            @else
                <div class="space-y-3">
                    @foreach ($verificationHistory as $item)
                        @php
                            $itemStatusClass = match ($item->status) {
                                'pending' => 'bg-amber-50 text-amber-700',
                                'verified' => 'bg-emerald-50 text-emerald-700',
                                'rejected' => 'bg-red-50 text-red-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <article class="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-semibold text-slate-900">{{ $documentTypes[$item->document_type] ?? \Illuminate\Support\Str::headline($item->document_type) }}</p>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $itemStatusClass }}">{{ \Illuminate\Support\Str::headline($item->status) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('M j, Y g:i A') : '-' }}</p>
                            @if ($item->status === 'rejected' && $item->rejection_reason)
                                <p class="mt-2 text-xs text-red-700">{{ $item->rejection_reason }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </x-dashboard.card>
    </aside>
</div>
@endcomponent
