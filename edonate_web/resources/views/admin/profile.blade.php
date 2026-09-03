@extends('layouts.admin')

@php
    $profile = $adminProfile ?? [];
    $profileName = trim((string) ($profile['full_name'] ?? session('admin_full_name') ?? session('admin_username') ?? 'Admin User'));
    $profileUsername = trim((string) ($profile['username'] ?? session('admin_username') ?? ''));
    $profileEmail = trim((string) ($profile['email'] ?? ''));
    $profileRole = trim((string) ($profile['role'] ?? 'Admin')) ?: 'Admin';
    $profileInitials = collect(preg_split('/\s+/', $profileName) ?: [])
        ->filter()
        ->take(2)
        ->map(static fn (string $part): string => strtoupper(substr($part, 0, 1)))
        ->implode('');
    $profileCreatedAt = trim((string) ($profile['created_at'] ?? ''));
    $twoFactorEnabled = (bool) ($profile['two_factor_enabled'] ?? false);
@endphp

@section('title', 'eDonate - Admin Profile')
@section('admin_page_class', 'admin-profile-page')
@section('header_title', 'Admin Profile')
@section('header_subtitle', 'View your administrator account and security status')

@section('header_actions')
    <a class="btn btn-outline-danger btn-sm" href="{{ route('admin.settings') }}">
        <i class="bi bi-gear me-1" aria-hidden="true"></i>
        Open Settings
    </a>
@endsection
@section('main_content')
    <div class="container-fluid px-0">
        <div class="row g-4">
            <div class="col-12 col-xl-4">
                <section class="card h-100 shadow-sm" aria-labelledby="profileSummaryTitle">
                    <div class="card-body d-flex flex-column align-items-center p-4 text-center">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger-emphasis fw-semibold fs-2"
                            style="width: 6rem; height: 6rem;"
                            aria-hidden="true">
                            {{ $profileInitials ?: 'ED' }}
                        </div>

                        <h2 class="h4 mt-3 mb-1" id="profileSummaryTitle">{{ $profileName }}</h2>
                        <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis">{{ $profileRole }}</span>

                        <dl class="w-100 text-start mt-4 mb-0">
                            <div class="border-bottom py-3">
                                <dt class="small text-body-secondary mb-1">Username</dt>
                                <dd class="mb-0 text-break">{{ $profileUsername ?: 'Not set' }}</dd>
                            </div>
                            <div class="border-bottom py-3">
                                <dt class="small text-body-secondary mb-1">Email</dt>
                                <dd class="mb-0 text-break">{{ $profileEmail ?: 'Not set' }}</dd>
                            </div>
                            <div class="py-3">
                                <dt class="small text-body-secondary mb-1">Account ID</dt>
                                <dd class="mb-0">#{{ (int) ($profile['admin_id'] ?? 0) }}</dd>
                            </div>
                        </dl>

                        <div class="mt-auto w-100 pt-3">
                            <a class="btn btn-danger w-100" href="{{ route('admin.rbac') }}">
                                <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>
                                Edit Profile
                            </a>
                            <p class="small text-body-secondary mt-2 mb-0">
                                Profile name, username, and email are managed in User Roles and Permissions.
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-8">
                <div class="row g-4">
                    <div class="col-12">
                        <section class="card shadow-sm" aria-labelledby="accountInformationTitle">
                            <div class="card-header bg-transparent d-flex align-items-center gap-2">
                                <i class="bi bi-person-vcard text-danger" aria-hidden="true"></i>
                                <h2 class="h5 mb-0" id="accountInformationTitle">Account Information</h2>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <div class="small text-body-secondary">Full name</div>
                                        <div class="fw-semibold text-break">{{ $profileName }}</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="small text-body-secondary">Email address</div>
                                        <div class="fw-semibold text-break">{{ $profileEmail ?: 'Not set' }}</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="small text-body-secondary">Role</div>
                                        <div class="fw-semibold">{{ $profileRole }}</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="small text-body-secondary">Member since</div>
                                        <div class="fw-semibold">
                                            {{ $profileCreatedAt !== '' ? \Illuminate\Support\Carbon::parse($profileCreatedAt)->format('M d, Y') : 'Not available' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="col-12">
                        <section class="card shadow-sm" aria-labelledby="profileSecurityTitle">
                            <div class="card-header bg-transparent d-flex align-items-center gap-2">
                                <i class="bi bi-shield-check text-danger" aria-hidden="true"></i>
                                <h2 class="h5 mb-0" id="profileSecurityTitle">Security</h2>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                    <div>
                                        <h3 class="h6 mb-1">Google Authenticator</h3>
                                        <p class="text-body-secondary mb-0">
                                            {{ $twoFactorEnabled ? 'Two-factor authentication is enabled for this account.' : 'Two-factor authentication is not enrolled for this account.' }}
                                        </p>
                                    </div>
                                    <a class="btn btn-outline-danger" href="{{ route('admin.2fa.setup') }}">
                                        <i class="bi bi-qr-code me-1" aria-hidden="true"></i>
                                        Manage 2FA
                                    </a>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
