@extends('layouts.admin')

@section('title', 'eDonate - Unauthorized')
@section('admin_page_class', 'admin-unauthorized-page')
@section('header_title', 'Unauthorized Access')
@section('header_subtitle', 'You do not have permission to view this page')

@section('admin_page_data')
{!! json_encode([
	'page' => 'admin-unauthorized',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	@php
		$currentRole = strtolower((string) session('admin_role', ''));
		$homeRoute = $currentRole === 'staff' ? route('staff.dashboard') : route('admin.dashboard');
	@endphp

	<div class="main container-fluid px-0">
		<main class="content container-fluid py-3">
			<div class="card border-0 shadow-sm">
				<div class="card-body p-4 p-md-5">
					<h2 class="h4 mb-3">Access denied</h2>
                    <p class="text-muted mb-4">Your account role is not authorized to open this module. Please return to your dashboard or sign out and log in with an account that has permission.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ $homeRoute }}" class="btn btn-danger">Go to Dashboard</a>
                        <button type="button" class="btn btn-outline-secondary" data-logout-confirm>Sign out</button>
                    </div>
				</div>
			</div>
		</main>
	</div>
@endsection
