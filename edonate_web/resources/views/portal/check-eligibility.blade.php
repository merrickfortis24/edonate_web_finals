@php
    $status = strtolower((string) ($latestEligibility?->status ?? 'for_review'));
    $statusLabels = [
        'eligible' => 'Eligible',
        'not_eligible' => 'Not Eligible',
        'not eligible' => 'Not Eligible',
        'temporary_deferred' => 'Temporarily Deferred',
        'temporary deferred' => 'Temporarily Deferred',
        'for_review' => 'For Review',
        'for review' => 'For Review',
        'approved' => 'Eligible',
        'declined' => 'Not Eligible',
        'pending' => 'For Review',
    ];
    $statusClasses = [
        'eligible' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'not_eligible' => 'bg-red-50 text-red-700 ring-red-200',
        'not eligible' => 'bg-red-50 text-red-700 ring-red-200',
        'temporary_deferred' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'temporary deferred' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'for_review' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'for review' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'declined' => 'bg-red-50 text-red-700 ring-red-200',
        'pending' => 'bg-sky-50 text-sky-700 ring-sky-200',
    ];
    $statusLabel = $statusLabels[$status] ?? 'For Review';
    $statusClass = $statusClasses[$status] ?? 'bg-slate-50 text-slate-700 ring-slate-200';
@endphp

@component('layouts.donor-portal', [
    'pageTitle' => 'Check Eligibility',
    'pageHeading' => 'Eligibility Status',
    'pageSubheading' => 'Answer the screening questions to get your initial donation eligibility result.',
    'navLinks' => $navLinks,
    'activeNav' => $activeNav,
    'user' => $user,
    'totalDonations' => $totalDonations,
])
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-1">
        <x-dashboard.card title="Current Eligibility" subtitle="Based on your latest screening record.">
            <div class="grid gap-4">
                <div class="rounded-xl p-4 ring-1 {{ $statusClass }}">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em]">Status</p>
                    <p class="mt-1 text-xl font-bold">{{ $statusLabel }}</p>
                </div>

                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Next Eligible Date</p>
                    <p class="mt-1 text-xl font-bold text-red-700">{{ $nextEligibleDate ? \Carbon\Carbon::parse($nextEligibleDate)->format('F j, Y') : 'To be determined' }}</p>
                </div>

                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Last Donation Date</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $latestDonationDate ? \Carbon\Carbon::parse($latestDonationDate)->format('F j, Y') : 'No donation records yet' }}</p>
                </div>

                @if ($latestEligibility?->result_reason)
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Reason</p>
                        <p class="mt-1 text-sm font-medium leading-6 text-slate-800">{{ $latestEligibility->result_reason }}</p>
                    </div>
                @endif

                @if ($latestEligibility?->recommendation_message)
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Recommendation</p>
                        <p class="mt-1 text-sm font-medium leading-6 text-slate-800">{{ $latestEligibility->recommendation_message }}</p>
                    </div>
                @endif
            </div>
        </x-dashboard.card>
    </div>

    <div class="lg:col-span-2">
        <x-dashboard.card title="Eligibility Screening" subtitle="Please answer every active screening question.">
            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (($screeningQuestions ?? collect())->isEmpty())
                <div class="rounded-xl bg-slate-50 p-4 text-sm font-medium text-slate-600">
                    No active screening questions are available right now.
                </div>
            @else
                <form method="POST" action="{{ route('donor.check-eligibility.submit') }}" class="space-y-5">
                    @csrf
                    <x-privacy-acknowledgment purpose="health-screening" />

                    @foreach ($screeningQuestions as $question)
                        @php
                            $questionId = (int) $question->question_id;
                            $oldAnswer = old("answers.{$questionId}");
                            $oldFollowup = old("followups.{$questionId}");
                        @endphp

                        <fieldset class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <legend class="px-1 text-sm font-bold leading-6 text-slate-900">
                                {{ $question->question_order }}. {{ $question->question_text }}
                            </legend>

                            <div class="mt-3 flex flex-wrap gap-3">
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-red-300">
                                    <input type="radio" name="answers[{{ $questionId }}]" value="yes" required class="h-4 w-4 border-slate-300 text-red-700 focus:ring-red-700" @checked($oldAnswer === 'yes')>
                                    Yes
                                </label>
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-red-300">
                                    <input type="radio" name="answers[{{ $questionId }}]" value="no" required class="h-4 w-4 border-slate-300 text-red-700 focus:ring-red-700" @checked($oldAnswer === 'no')>
                                    No
                                </label>
                            </div>

                            @if ($question->followup_prompt)
                                <label class="mt-4 block text-sm font-semibold text-slate-700" for="followup-{{ $questionId }}">
                                    {{ $question->followup_prompt }}
                                </label>
                                <textarea id="followup-{{ $questionId }}" name="followups[{{ $questionId }}]" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-100">{{ $oldFollowup }}</textarea>
                            @endif
                        </fieldset>
                    @endforeach

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-red-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            Submit Screening
                        </button>
                    </div>
                </form>
            @endif
        </x-dashboard.card>
    </div>
</div>
@endcomponent
