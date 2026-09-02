@props([
    'donor',
    'fieldPrefix' => null,
])

@php
    $donorName = data_get($donor, 'name')
        ?: data_get($donor, 'full_name')
        ?: collect([
            data_get($donor, 'first_name'),
            data_get($donor, 'middle_name'),
            data_get($donor, 'last_name'),
        ])->filter(static fn ($part): bool => filled($part))->implode(' ');

    $donorName = trim((string) $donorName) ?: 'Unnamed donor';

    $bloodType = data_get($donor, 'blood_type')
        ?: data_get($donor, 'bloodType.blood_type')
        ?: 'Not yet determined';

    $donorId = data_get($donor, 'donor_id') ?? data_get($donor, 'id') ?? '—';
    $phone = data_get($donor, 'contact_number') ?: data_get($donor, 'phone');
    $photoUrl = data_get($donor, 'photo_url')
        ?: data_get($donor, 'profile_photo_url')
        ?: data_get($donor, 'photo');

    $locationParts = collect([
        data_get($donor, 'street_address'),
        data_get($donor, 'location.street_address'),
        data_get($donor, 'barangay_name'),
        data_get($donor, 'location.barangay_name'),
        data_get($donor, 'city'),
        data_get($donor, 'location.city'),
        data_get($donor, 'province'),
        data_get($donor, 'location.province'),
    ])->filter(static fn ($part): bool => filled($part))->unique()->values();

    $address = data_get($donor, 'address') ?: $locationParts->implode(', ');
    $address = trim((string) $address) ?: 'Address not provided';

    $parseDate = static function ($value): ?\Carbon\Carbon {
        if (blank($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    };

    $lastDonationDate = $parseDate(data_get($donor, 'last_donation_date'));
    $nextEligibleDate = $parseDate(data_get($donor, 'next_eligible_date'));
    $isEligible = ! $nextEligibleDate || $nextEligibleDate->startOfDay()->lte(now()->startOfDay());

    $initials = collect(preg_split('/\s+/', $donorName) ?: [])
        ->filter()
        ->take(2)
        ->map(static fn (string $part): string => strtoupper(substr($part, 0, 1)))
        ->implode('');

    $initials = $initials ?: 'ID';

    $verificationStatus = strtolower((string) data_get($donor, 'verification_status', 'unverified'));
    $verificationStatus = in_array($verificationStatus, ['verified', 'pending', 'rejected', 'unverified'], true)
        ? $verificationStatus
        : 'unverified';

    $verificationLabel = [
        'verified' => 'VERIFIED DONOR',
        'pending' => 'PENDING VERIFICATION',
        'rejected' => 'REJECTED',
        'unverified' => 'UNVERIFIED DONOR',
    ][$verificationStatus];

    $eligibilityStatus = strtolower((string) data_get($donor, 'eligibility_status', 'eligible'));
    $eligibilityLabel = $eligibilityStatus === 'not_eligible' ? 'Not Eligible' : 'Eligible';
    $fieldId = static fn (string $suffix): ?string => filled($fieldPrefix)
        ? trim((string) $fieldPrefix).$suffix
        : null;
@endphp

<article class="digital-id-card digital-id-card--component mx-auto w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-red-950/10 ring-1 ring-black/5" data-digital-id-card aria-label="eDonate Digital ID card">
    <!-- Card Header (Red Banner) -->
    <header class="digital-id-card__header relative flex items-center justify-between overflow-hidden bg-red-700 px-5 py-3 text-white">
        <div class="digital-id-card__header-left flex items-center gap-3">
            <svg class="digital-id-card__drop-icon h-8 w-8 shrink-0 text-white fill-current" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
            </svg>
            <div>
                <p class="digital-id-card__brand text-xl font-bold tracking-tight leading-tight text-white m-0">eDonate Digital ID</p>
                <p class="digital-id-card__subtitle text-xs font-normal text-red-100 m-0">City Health Office, Lipa City</p>
            </div>
        </div>

        <div class="digital-id-card__header-right">
            <div class="digital-id-card__status-pill flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-bold shadow-sm">
                <svg class="h-4 w-4 shrink-0 text-emerald-600 fill-current" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span
                    class="digital-id-card__status digital-id-card__status--{{ $verificationStatus }} text-emerald-700"
                    data-digital-id-field="verificationBadge"
                    @if ($fieldId('VerificationBadge')) id="{{ $fieldId('VerificationBadge') }}" @endif
                >{{ $verificationLabel }}</span>
            </div>
        </div>
    </header>

    <!-- Card Body (3-Column Layout) -->
    <div class="digital-id-card__body grid grid-cols-12 bg-white">
        <!-- Left Column: Avatar, Name, ID, Blood Type, Account Status -->
        <div class="digital-id-card__col digital-id-card__col--left col-span-12 sm:col-span-4 flex flex-col items-center justify-between border-b sm:border-b-0 sm:border-r border-slate-200 p-4 text-center">
            <div class="digital-id-card__avatar flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full border-[3px] border-red-700 bg-red-100 text-xl font-extrabold text-red-700 shadow-inner">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="{{ $donorName }} profile photo" class="h-full w-full object-cover">
                @else
                    <span
                        data-digital-id-field="avatar"
                        @if ($fieldId('Avatar')) id="{{ $fieldId('Avatar') }}" @endif
                        aria-hidden="true"
                    >{{ $initials }}</span>
                    <span class="sr-only">No profile photo available</span>
                @endif
            </div>

            <div class="digital-id-card__identity-info mt-2">
                <h2
                    class="digital-id-card__name text-base font-extrabold leading-snug text-slate-950 m-0 break-words"
                    data-digital-id-field="name"
                    @if ($fieldId('Name')) id="{{ $fieldId('Name') }}" @endif
                >{{ $donorName }}</h2>
                <p class="digital-id-card__code text-xs font-semibold text-slate-600 m-0 mt-0.5">ID:
                    <span
                        data-digital-id-field="code"
                        @if ($fieldId('Code')) id="{{ $fieldId('Code') }}" @endif
                    >{{ $donorId }}</span>
                </p>
            </div>

            <div class="digital-id-card__blood-type mt-2 w-full max-w-[7.5rem] rounded-lg border-2 border-red-700 bg-white py-1 px-2 text-center">
                <span class="digital-id-card__blood-type-label block text-[0.6rem] font-extrabold uppercase tracking-wider text-slate-900 leading-tight">BLOOD TYPE</span>
                <strong
                    class="digital-id-card__blood-type-value block text-2xl font-black text-slate-950 leading-tight mt-0.5"
                    data-digital-id-field="bloodType"
                    @if ($fieldId('BloodType')) id="{{ $fieldId('BloodType') }}" @endif
                >{{ $bloodType }}</strong>
            </div>

            <div
                class="digital-id-card__account mt-2 inline-block rounded-full bg-slate-300 px-2.5 py-0.5 text-[0.55rem] font-extrabold uppercase tracking-wide text-slate-800"
                data-digital-id-field="accountStatus"
                @if ($fieldId('AccountStatus')) id="{{ $fieldId('AccountStatus') }}" @endif
            >{{ data_get($donor, 'is_active', true) === false ? 'INACTIVE ACCOUNT' : 'ACTIVE ACCOUNT' }}</div>
        </div>

        <!-- Middle Column: Address, Contact Number, Last Donation & Next Eligible -->
        <div class="digital-id-card__col digital-id-card__col--middle col-span-12 sm:col-span-5 flex flex-col border-b sm:border-b-0 sm:border-r border-slate-200">
            <div class="digital-id-card__field digital-id-card__field--address flex-1 border-b border-slate-200 p-3">
                <span class="digital-id-card__field-label block text-xs font-medium text-slate-500">Address</span>
                <span
                    class="digital-id-card__field-value mt-1 block text-sm font-bold text-slate-950 leading-snug break-words"
                    data-digital-id-field="address"
                    @if ($fieldId('Address')) id="{{ $fieldId('Address') }}" @endif
                >{{ $address }}</span>
            </div>

            <div class="digital-id-card__field digital-id-card__field--contact flex-1 border-b border-slate-200 p-3">
                <span class="digital-id-card__field-label block text-xs font-medium text-slate-500">Contact Number</span>
                <span
                    class="digital-id-card__field-value mt-1 block text-sm font-bold text-slate-950 leading-snug break-words"
                    data-digital-id-field="contactNumber"
                    @if ($fieldId('ContactNumber')) id="{{ $fieldId('ContactNumber') }}" @endif
                >{{ $phone ?: 'Contact number not provided' }}</span>
            </div>

            <div class="digital-id-card__dates-row grid grid-cols-2">
                <div class="digital-id-card__date-col border-r border-slate-200 p-3">
                    <span class="digital-id-card__field-label block text-[0.62rem] font-bold uppercase tracking-wider text-slate-500">LAST DONATION</span>
                    <span
                        class="digital-id-card__field-value mt-1 block text-xs font-bold text-slate-950"
                        data-digital-id-field="lastDonationDate"
                        @if ($fieldId('LastDonationDate')) id="{{ $fieldId('LastDonationDate') }}" @endif
                    >{{ $lastDonationDate?->format('M d, Y') ?? 'None recorded' }}</span>
                </div>

                <div class="digital-id-card__date-col p-3">
                    <span class="digital-id-card__field-label block text-[0.62rem] font-bold uppercase tracking-wider text-slate-500">NEXT ELIGIBLE</span>
                    <span
                        class="digital-id-card__field-value digital-id-card__field-value--{{ $isEligible ? 'eligible' : 'waiting' }} mt-1 block text-xs font-bold {{ $isEligible ? 'text-emerald-600' : 'text-amber-600' }}"
                        data-digital-id-field="nextEligibleDate"
                        @if ($fieldId('NextEligibleDate')) id="{{ $fieldId('NextEligibleDate') }}" @endif
                    >{{ $nextEligibleDate?->format('M d, Y') ?? 'Eligible now' }}</span>
                </div>
            </div>
        </div>

        <!-- Right Column: Eligibility Status, Identity Status, QR Code -->
        <div class="digital-id-card__col digital-id-card__col--right col-span-12 sm:col-span-3 flex flex-col items-center justify-between p-3.5 text-center">
            <div class="digital-id-card__status-stack w-full flex flex-col items-center gap-2">
                <div class="digital-id-card__status-item w-full flex flex-col items-center">
                    <span
                        class="digital-id-card__badge digital-id-card__badge--{{ $isEligible ? 'eligible' : 'waiting' }} inline-block rounded bg-emerald-700 px-3 py-0.5 text-xs font-extrabold text-white tracking-wide uppercase"
                        data-digital-id-field="eligibility"
                        @if ($fieldId('Eligibility')) id="{{ $fieldId('Eligibility') }}" @endif
                    >{{ strtoupper($eligibilityLabel) }}</span>
                    <span class="digital-id-card__badge-sub text-[0.62rem] font-medium text-slate-700 mt-0.5">Identity Status</span>
                </div>

                <div class="digital-id-card__status-item w-full flex flex-col items-center">
                    <span
                        class="digital-id-card__badge digital-id-card__badge--verified inline-block rounded bg-emerald-700 px-3 py-0.5 text-xs font-extrabold text-white tracking-wide uppercase"
                        data-digital-id-field="identity"
                        @if ($fieldId('Identity')) id="{{ $fieldId('Identity') }}" @endif
                    >{{ strtoupper($verificationStatus === 'verified' ? 'VERIFIED' : $verificationStatus) }}</span>
                    <span class="digital-id-card__badge-sub text-[0.62rem] font-medium text-slate-700 mt-0.5">Identity Status</span>
                </div>
            </div>

            <!-- Realistic QR Matrix -->
            <div class="digital-id-card__qr-section mt-2 flex flex-col items-center">
                <div class="digital-id-card__qr-box h-20 w-20 flex items-center justify-center p-1 bg-white" aria-label="QR code placeholder">
                    <svg class="digital-id-card__qr-svg h-full w-full text-slate-900" viewBox="0 0 100 100" fill="currentColor" aria-hidden="true">
                        <!-- Top-Left Finder -->
                        <rect x="6" y="6" width="26" height="26" rx="3" fill="none" stroke="currentColor" stroke-width="4" />
                        <rect x="13" y="13" width="12" height="12" rx="1.5" fill="currentColor" />
                        <!-- Top-Right Finder -->
                        <rect x="68" y="6" width="26" height="26" rx="3" fill="none" stroke="currentColor" stroke-width="4" />
                        <rect x="75" y="13" width="12" height="12" rx="1.5" fill="currentColor" />
                        <!-- Bottom-Left Finder -->
                        <rect x="6" y="68" width="26" height="26" rx="3" fill="none" stroke="currentColor" stroke-width="4" />
                        <rect x="13" y="75" width="12" height="12" rx="1.5" fill="currentColor" />
                        <!-- Timing & Data Dots -->
                        <rect x="38" y="8" width="4.5" height="4.5" rx="1" />
                        <rect x="46" y="8" width="4.5" height="4.5" rx="1" />
                        <rect x="54" y="8" width="4.5" height="4.5" rx="1" />
                        <rect x="38" y="18" width="4.5" height="4.5" rx="1" />
                        <rect x="54" y="18" width="4.5" height="4.5" rx="1" />
                        <rect x="38" y="26" width="4.5" height="4.5" rx="1" />
                        <rect x="46" y="26" width="4.5" height="4.5" rx="1" />
                        <rect x="8" y="38" width="4.5" height="4.5" rx="1" />
                        <rect x="18" y="38" width="4.5" height="4.5" rx="1" />
                        <rect x="26" y="38" width="4.5" height="4.5" rx="1" />
                        <rect x="68" y="38" width="4.5" height="4.5" rx="1" />
                        <rect x="78" y="38" width="4.5" height="4.5" rx="1" />
                        <rect x="88" y="38" width="4.5" height="4.5" rx="1" />
                        <rect x="8" y="48" width="4.5" height="4.5" rx="1" />
                        <rect x="22" y="48" width="4.5" height="4.5" rx="1" />
                        <rect x="72" y="48" width="4.5" height="4.5" rx="1" />
                        <rect x="88" y="48" width="4.5" height="4.5" rx="1" />
                        <rect x="8" y="56" width="4.5" height="4.5" rx="1" />
                        <rect x="18" y="56" width="4.5" height="4.5" rx="1" />
                        <rect x="26" y="56" width="4.5" height="4.5" rx="1" />
                        <rect x="68" y="56" width="4.5" height="4.5" rx="1" />
                        <rect x="82" y="56" width="4.5" height="4.5" rx="1" />
                        <rect x="38" y="68" width="4.5" height="4.5" rx="1" />
                        <rect x="48" y="68" width="4.5" height="4.5" rx="1" />
                        <rect x="58" y="68" width="4.5" height="4.5" rx="1" />
                        <rect x="38" y="78" width="4.5" height="4.5" rx="1" />
                        <rect x="52" y="78" width="4.5" height="4.5" rx="1" />
                        <rect x="38" y="88" width="4.5" height="4.5" rx="1" />
                        <rect x="48" y="88" width="4.5" height="4.5" rx="1" />
                        <rect x="58" y="88" width="4.5" height="4.5" rx="1" />
                        <rect x="68" y="68" width="4.5" height="4.5" rx="1" />
                        <rect x="80" y="68" width="4.5" height="4.5" rx="1" />
                        <rect x="74" y="78" width="4.5" height="4.5" rx="1" />
                        <rect x="86" y="78" width="4.5" height="4.5" rx="1" />
                        <rect x="68" y="88" width="4.5" height="4.5" rx="1" />
                        <rect x="80" y="88" width="4.5" height="4.5" rx="1" />
                        <!-- Center Badge -->
                        <rect x="34" y="34" width="32" height="32" rx="4" fill="#ffffff" stroke="currentColor" stroke-width="2.5" />
                        <text x="50" y="56" font-family="system-ui, -apple-system, sans-serif" font-size="14" font-weight="900" text-anchor="middle" fill="currentColor">QR</text>
                    </svg>
                </div>
                <p class="digital-id-card__qr-text text-[0.65rem] font-semibold text-slate-800 m-0 mt-0.5">Scan to verify donor</p>
            </div>
        </div>
    </div>
</article>
