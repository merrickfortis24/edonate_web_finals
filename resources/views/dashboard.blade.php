<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Donor Dashboard | Blood Donation Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        :root {
            --health-red: #c62f3c;
            --ink-900: #1f2937;
            --ink-500: #6b7280;
            --line-soft: #e5e7eb;
            --surface: #ffffff;
            --shadow-soft: 0 16px 40px rgba(17, 24, 39, 0.08);
        }

        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(circle at 10% -10%, #ffe6e9 0, #ffe6e9 18%, transparent 40%),
                radial-gradient(circle at 90% -20%, #fff5f5 0, #fff5f5 15%, transparent 35%),
                #f8fafc;
            color: var(--ink-900);
            min-height: 100vh;
        }

        .dashboard-shell {
            max-width: 70rem;
            margin: 1.25rem auto;
            padding: 0 0.75rem;
        }

        .panel {
            background: var(--surface);
            border: 1px solid rgba(198, 47, 60, 0.12);
            border-radius: 1rem;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .panel-head {
            background: linear-gradient(135deg, #fff 0%, #fff7f8 100%);
            border-bottom: 1px solid var(--line-soft);
            padding: 1.2rem 1rem;
        }

        .panel-body {
            padding: 1rem;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        @media (min-width: 768px) {
            .dashboard-shell {
                margin: 2rem auto;
                padding: 0 1rem;
            }

            .panel-head {
                padding: 1.5rem 1.5rem;
            }

            .panel-body {
                padding: 1.5rem;
            }

            .profile-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
<div class="dashboard-shell">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Welcome, {{ $donor->first_name }}</h1>
            <p class="text-secondary mb-0">Manage your donor profile and appointments.</p>
        </div>
        <form method="POST" action="{{ route('donor.logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Logout</button>
        </form>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    @if (!$accessUnlocked)
        <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
            <div>
                <strong>Access requirements not complete</strong><br>
                Complete profile, accept Terms, and verify OTP to unlock donor features and appointment booking.
            </div>
            <a href="#access-gate" class="btn btn-danger">Review requirements</a>
        </div>
    @endif

    <section class="panel mb-3" id="access-gate" aria-labelledby="access-gate-title">
        <header class="panel-head">
            <h2 id="access-gate-title" class="h5 mb-1">Account Access Gate</h2>
            <p class="text-secondary mb-0">Google sign-in is immediate, but sensitive features stay locked until all checks pass.</p>
        </header>
        <div class="panel-body">
            <div class="profile-grid mb-3">
                <div>
                    <strong>1. Profile Completion:</strong>
                    <span class="{{ $profileComplete ? 'text-success' : 'text-danger' }}">{{ $profileComplete ? 'Completed' : 'Pending' }}</span>
                </div>
                <div>
                    <strong>2. Terms Acceptance:</strong>
                    <span class="{{ $termsAccepted ? 'text-success' : 'text-danger' }}">{{ $termsAccepted ? 'Accepted' : 'Pending' }}</span>
                </div>
                <div>
                    <strong>3. OTP Verification:</strong>
                    <span class="{{ $otpVerified ? 'text-success' : 'text-danger' }}">{{ $otpVerified ? 'Verified' : 'Pending' }}</span>
                </div>
                <div>
                    <strong>Feature Access:</strong>
                    <span class="{{ $accessUnlocked ? 'text-success' : 'text-danger' }}">{{ $accessUnlocked ? 'Unlocked' : 'Locked' }}</span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#completeProfileModal" {{ $profileComplete ? 'disabled' : '' }}>
                    {{ $profileComplete ? 'Profile Completed' : 'Complete Profile' }}
                </button>

                <form method="POST" action="{{ route('donor.dashboard.accept-terms') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger" {{ $termsAccepted ? 'disabled' : '' }}>
                        {{ $termsAccepted ? 'Terms Accepted' : 'Accept Terms & Privacy' }}
                    </button>
                </form>

                <form method="POST" action="{{ route('donor.dashboard.send-otp') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary" {{ $otpVerified ? 'disabled' : '' }}>
                        {{ $otpVerified ? 'OTP Verified' : 'Send OTP' }}
                    </button>
                </form>
            </div>

            @if (!$otpVerified)
                <form method="POST" action="{{ route('donor.dashboard.verify-otp') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12 col-md-4">
                        <label for="access_otp" class="form-label mb-1">Enter OTP</label>
                        <input type="text" id="access_otp" name="otp" class="form-control" maxlength="6" inputmode="numeric" pattern="\d{6}" required>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-danger">Verify OTP</button>
                    </div>
                    <div class="col-12">
                        <small class="text-secondary">Code expires in 10 minutes. Max 5 invalid attempts.</small>
                    </div>
                </form>
            @endif
        </div>
    </section>

    <section class="panel" aria-labelledby="profile-title">
        <header class="panel-head">
            <h2 id="profile-title" class="h5 mb-1">Profile Summary</h2>
            <p class="text-secondary mb-0">Your current account details from MySQL records.</p>
        </header>
        <div class="panel-body">
            <div class="profile-grid">
                <div><strong>Name:</strong> {{ trim($donor->first_name . ' ' . $donor->last_name) }}</div>
                <div><strong>Email:</strong> {{ session('donor_email') }}</div>
                <div><strong>Phone:</strong> {{ $donor->contact_number ?: '-' }}</div>
                <div><strong>Birthdate:</strong> {{ $donor->birthdate ?: '-' }}</div>
                <div><strong>Gender:</strong> {{ $donor->gender ?: '-' }}</div>
                <div><strong>Blood Type ID:</strong> {{ $donor->blood_type_id ?: '-' }}</div>
                <div><strong>Street:</strong> {{ $location?->street_address ?: '-' }}</div>
                <div><strong>Barangay:</strong> {{ $location?->barangay_name ?: '-' }}</div>
                <div><strong>City:</strong> {{ $location?->city ?: '-' }}</div>
                <div><strong>Province:</strong> {{ $location?->province ?: '-' }}</div>
            </div>
        </div>
    </section>

    <section class="panel mt-3" aria-labelledby="features-title">
        <header class="panel-head">
            <h2 id="features-title" class="h5 mb-1">Donor Features</h2>
            <p class="text-secondary mb-0">Profile completion unlocks operational features.</p>
        </header>
        <div class="panel-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong>Appointment Booking</strong>
                <div class="text-secondary small">
                    @if ($accessUnlocked)
                        All requirements are complete. You can proceed with booking.
                    @else
                        Complete profile, accept Terms, and verify OTP to book and manage appointments.
                    @endif
                </div>
            </div>
            <button type="button" class="btn btn-danger" {{ $accessUnlocked ? '' : 'disabled' }}>
                Book Appointment
            </button>
        </div>
    </section>
</div>

<div class="modal fade" id="completeProfileModal" tabindex="-1" aria-labelledby="completeProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('donor.profile.complete') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="completeProfileModalLabel">Complete Your Donor Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="{{ old('phone', $donor->contact_number) }}" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="birthdate" class="form-label">Birthdate</label>
                            <input type="date" id="birthdate" name="birthdate" class="form-control" value="{{ old('birthdate', $donor->birthdate) }}" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="gender" class="form-label">Gender</label>
                            <select id="gender" name="gender" class="form-select" required>
                                <option value="" disabled {{ old('gender', $donor->gender) ? '' : 'selected' }}>Select gender</option>
                                @foreach (['Male', 'Female', 'Other', 'Prefer not to say'] as $gender)
                                    <option value="{{ $gender }}" {{ old('gender', $donor->gender) === $gender ? 'selected' : '' }}>{{ $gender }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="blood_type" class="form-label">Blood Type</label>
                            <select id="blood_type" name="blood_type" class="form-select" required>
                                <option value="" disabled {{ old('blood_type') ? '' : 'selected' }}>Select blood type</option>
                                @foreach ($bloodTypes as $type)
                                    <option value="{{ $type }}" {{ old('blood_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="street_address" class="form-label">Street Address</label>
                            <input type="text" id="street_address" name="street_address" class="form-control" value="{{ old('street_address', $location?->street_address) }}" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="barangay" class="form-label">Barangay</label>
                            <input type="text" id="barangay" name="barangay" class="form-control" value="{{ old('barangay', $location?->barangay_name) }}" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="city" class="form-label">Municipality/City</label>
                            <input type="text" id="city" name="city" class="form-control" value="{{ old('city', $location?->city) }}" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="province" class="form-label">Province</label>
                            <input type="text" id="province" name="province" class="form-control" value="{{ old('province', $location?->province) }}" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Save Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
@if ((!$profileComplete && !$accessUnlocked) || $errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modalElement = document.getElementById('completeProfileModal');
        if (!modalElement) {
            return;
        }
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
    });
</script>
@endif
</body>
</html>
