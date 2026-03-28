@extends('layouts.admin')

@section('title', 'eDonate - User Management')
@section('admin_page_class', 'admin-users-page')
@section('sidebar_link_mode', 'link')
@section('hamburger_id', 'hamburger')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')

@section('header_title', 'User Management')
@section('header_subtitle', 'Manage donor registration, updates, and account validation')

@section('header_actions')
	<button class="btn-export" aria-label="Export donor data">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
			<polyline points="17 8 12 3 7 8"/>
			<line x1="12" y1="3" x2="12" y2="15"/>
		</svg>
		Export Data
	</button>
@endsection

@section('admin_page_data')
@json([
	'page' => 'user-management',
])
@endsection

@section('main_content')
<main class="main">
	<div class="content">
		<section class="stats" aria-label="Donor statistics">
			<div class="stat-card stat-card--red">
				<span class="stat-card__label">Total Donors</span>
				<span class="stat-card__value">9</span>
			</div>
			<div class="stat-card stat-card--green">
				<span class="stat-card__label">Eligible Donors</span>
				<span class="stat-card__value">6</span>
			</div>
			<div class="stat-card stat-card--blue">
				<span class="stat-card__label">Not Eligible</span>
				<span class="stat-card__value">3</span>
			</div>
			<div class="stat-card stat-card--gold">
				<span class="stat-card__label">Total Donations</span>
				<span class="stat-card__value">97</span>
			</div>
		</section>

		<div class="filter-bar" role="search">
			<div class="filter-bar__search">
				<span class="filter-bar__search-icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.45)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
					</svg>
				</span>
				<input type="text" class="filter-bar__search-input" placeholder="Search by name, ID, or email..." aria-label="Search donors">
			</div>

			<div class="filter-bar__dropdown">
				<span class="filter-bar__dropdown-icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 4.5C12 4.5 6.5 11.5 6.5 16C6.5 19.038 9.014 21.5 12 21.5C14.986 21.5 17.5 19.038 17.5 16C17.5 11.5 12 4.5 12 4.5Z" fill="#b60c0c"/>
					</svg>
				</span>
				<select class="filter-bar__select filter-bar__select--blood" aria-label="Filter by blood type">
					<option>All Blood Types</option>
					<option>A+</option><option>A-</option><option>B+</option><option>B-</option>
					<option>AB+</option><option>AB-</option><option>O+</option><option>O-</option>
				</select>
				<span class="filter-bar__dropdown-arrow" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="6 9 12 15 18 9"/>
					</svg>
				</span>
			</div>

			<div class="filter-bar__dropdown">
				<span class="filter-bar__dropdown-icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
					</svg>
				</span>
				<select class="filter-bar__select filter-bar__select--status" aria-label="Filter by status">
					<option>All Status</option>
					<option>Eligible</option>
					<option>Not Eligible</option>
				</select>
				<span class="filter-bar__dropdown-arrow" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="6 9 12 15 18 9"/>
					</svg>
				</span>
			</div>
		</div>

		<section class="table-wrap" aria-label="Donor list">
			<div class="table-inner">
				<div class="table-grid table-thead">
					<div class="table-th">Donor ID</div>
					<div class="table-th">Name</div>
					<div class="table-th">Blood Type</div>
					<div class="table-th">Contact</div>
					<div class="table-th">Last Donation</div>
					<div class="table-th">Status</div>
					<div class="table-th cell-center">Donations</div>
					<div class="table-th">Actions</div>
				</div>

				<div class="table-body">
					<div class="table-grid table-row">
						<div class="table-td">D001</div>
						<div class="table-td">
							<div class="donor-name__primary">John Smith</div>
							<div class="donor-name__email">john.smith@gmail.com</div>
						</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#129656;</span> O+</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128222;</span> +63 912 304 4233</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128197;</span> Jan 10, 2026</div>
						<div class="table-td"><span class="badge badge--eligible">Eligible</span></div>
						<div class="table-td cell-center">33</div>
						<div class="table-td actions">
							<button class="btn-action btn-action--view" aria-label="View donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0063aa" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
							<button class="btn-action btn-action--edit" aria-label="Edit donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#129800" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></button>
							<button class="btn-action btn-action--delete" aria-label="Delete donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b60c0c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="11"/></svg></button>
						</div>
					</div>

					<div class="table-grid table-row">
						<div class="table-td">D002</div>
						<div class="table-td"><div class="donor-name__primary">James Reid</div><div class="donor-name__email">james.reid@gmail.com</div></div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#129656;</span> A+</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128222;</span> +63 915 332 1944</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128197;</span> Jan 11, 2026</div>
						<div class="table-td"><span class="badge badge--eligible">Eligible</span></div>
						<div class="table-td cell-center">3</div>
						<div class="table-td actions">
							<button class="btn-action btn-action--view" aria-label="View donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0063aa" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
							<button class="btn-action btn-action--edit" aria-label="Edit donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#129800" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></button>
							<button class="btn-action btn-action--delete" aria-label="Delete donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b60c0c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="11"/></svg></button>
						</div>
					</div>

					<div class="table-grid table-row">
						<div class="table-td">D003</div>
						<div class="table-td"><div class="donor-name__primary">Maria Cruz</div><div class="donor-name__email">maria.cruz@gmail.com</div></div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#129656;</span> AB-</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128222;</span> +63 918 112 0880</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128197;</span> Jan 12, 2026</div>
						<div class="table-td"><span class="badge badge--not-eligible">Not Eligible</span></div>
						<div class="table-td cell-center">5</div>
						<div class="table-td actions">
							<button class="btn-action btn-action--view" aria-label="View donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0063aa" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
							<button class="btn-action btn-action--edit" aria-label="Edit donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#129800" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></button>
							<button class="btn-action btn-action--delete" aria-label="Delete donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b60c0c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="11"/></svg></button>
						</div>
					</div>

					<div class="table-grid table-row">
						<div class="table-td">D004</div>
						<div class="table-td"><div class="donor-name__primary">Noah Reyes</div><div class="donor-name__email">noah.reyes@gmail.com</div></div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#129656;</span> O-</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128222;</span> +63 917 981 2241</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128197;</span> Jan 13, 2026</div>
						<div class="table-td"><span class="badge badge--eligible">Eligible</span></div>
						<div class="table-td cell-center">7</div>
						<div class="table-td actions">
							<button class="btn-action btn-action--view" aria-label="View donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0063aa" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
							<button class="btn-action btn-action--edit" aria-label="Edit donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#129800" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></button>
							<button class="btn-action btn-action--delete" aria-label="Delete donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b60c0c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="11"/></svg></button>
						</div>
					</div>

					<div class="table-grid table-row">
						<div class="table-td">D005</div>
						<div class="table-td"><div class="donor-name__primary">Paolo Lim</div><div class="donor-name__email">paolo.lim@gmail.com</div></div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#129656;</span> B+</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128222;</span> +63 916 445 9201</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128197;</span> Jan 14, 2026</div>
						<div class="table-td"><span class="badge badge--eligible">Eligible</span></div>
						<div class="table-td cell-center">9</div>
						<div class="table-td actions">
							<button class="btn-action btn-action--view" aria-label="View donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0063aa" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
							<button class="btn-action btn-action--edit" aria-label="Edit donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#129800" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></button>
							<button class="btn-action btn-action--delete" aria-label="Delete donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b60c0c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="11"/></svg></button>
						</div>
					</div>

					<div class="table-grid table-row">
						<div class="table-td">D006</div>
						<div class="table-td"><div class="donor-name__primary">Kevin Ong</div><div class="donor-name__email">kevin.ong@gmail.com</div></div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#129656;</span> AB+</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128222;</span> +63 918 551 0811</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128197;</span> Jan 15, 2026</div>
						<div class="table-td"><span class="badge badge--eligible">Eligible</span></div>
						<div class="table-td cell-center">11</div>
						<div class="table-td actions">
							<button class="btn-action btn-action--view" aria-label="View donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0063aa" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
							<button class="btn-action btn-action--edit" aria-label="Edit donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#129800" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></button>
							<button class="btn-action btn-action--delete" aria-label="Delete donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b60c0c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="11"/></svg></button>
						</div>
					</div>

					<div class="table-grid table-row">
						<div class="table-td">D007</div>
						<div class="table-td"><div class="donor-name__primary">Bianca Santos</div><div class="donor-name__email">bianca.santos@gmail.com</div></div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#129656;</span> A-</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128222;</span> +63 919 001 7212</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128197;</span> Jan 16, 2026</div>
						<div class="table-td"><span class="badge badge--not-eligible">Not Eligible</span></div>
						<div class="table-td cell-center">13</div>
						<div class="table-td actions">
							<button class="btn-action btn-action--view" aria-label="View donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0063aa" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
							<button class="btn-action btn-action--edit" aria-label="Edit donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#129800" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></button>
							<button class="btn-action btn-action--delete" aria-label="Delete donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b60c0c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="11"/></svg></button>
						</div>
					</div>

					<div class="table-grid table-row">
						<div class="table-td">D008</div>
						<div class="table-td"><div class="donor-name__primary">Ralph Dela Cruz</div><div class="donor-name__email">ralph.delacruz@gmail.com</div></div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#129656;</span> A-</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128222;</span> +63 917 730 2214</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128197;</span> Jan 17, 2026</div>
						<div class="table-td"><span class="badge badge--eligible">Eligible</span></div>
						<div class="table-td cell-center">55</div>
						<div class="table-td actions">
							<button class="btn-action btn-action--view" aria-label="View donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0063aa" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
							<button class="btn-action btn-action--edit" aria-label="Edit donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#129800" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></button>
							<button class="btn-action btn-action--delete" aria-label="Delete donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b60c0c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="11"/></svg></button>
						</div>
					</div>

					<div class="table-grid table-row">
						<div class="table-td">D009</div>
						<div class="table-td"><div class="donor-name__primary">Nina Que</div><div class="donor-name__email">nina.que@gmail.com</div></div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#129656;</span> A+</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128222;</span> +63 916 998 7710</div>
						<div class="table-td cell-inline"><span aria-hidden="true">&#128197;</span> Jan 18, 2026</div>
						<div class="table-td"><span class="badge badge--not-eligible">Not Eligible</span></div>
						<div class="table-td cell-center">1</div>
						<div class="table-td actions">
							<button class="btn-action btn-action--view" aria-label="View donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0063aa" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
							<button class="btn-action btn-action--edit" aria-label="Edit donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#129800" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></button>
							<button class="btn-action btn-action--delete" aria-label="Delete donor"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b60c0c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="11"/></svg></button>
						</div>
					</div>

					<nav class="pagination" aria-label="Table pagination">
						<span class="pagination__info">Showing 1-9 of 50 donors</span>
						<div class="pagination__controls">
							<button class="pagination__btn" aria-label="Previous page">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
							</button>
							<button class="pagination__btn pagination__btn--active" aria-label="Page 1" aria-current="page">1</button>
							<button class="pagination__btn" aria-label="Page 2">2</button>
							<button class="pagination__btn" aria-label="Next page">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
							</button>
						</div>
					</nav>
				</div>
			</div>
		</section>
	</div>
</main>

@endsection

@push('admin_scripts')
<script>
	(function () {
		document.querySelectorAll('.btn-export, .btn-action, .pagination__btn, a[href="#"]').forEach(function (element) {
			element.addEventListener('click', function (event) {
				if (element.getAttribute('href') === '#' || element.classList.contains('btn-export') || element.classList.contains('btn-action') || element.classList.contains('pagination__btn')) {
					event.preventDefault();
				}
			});
		});
	})();
</script>
@endpush

