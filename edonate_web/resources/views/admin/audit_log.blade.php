@extends('layouts.admin')

@section('title', 'eDonate - Audit Logs')
@section('admin_page_class', 'admin-audit-logs-page')
@section('header_title', 'Audit Logs')
@section('header_subtitle', 'Monitor and review all system activities and user actions')

@section('header_actions')
	<button class="audit-export-btn btn" id="auditExportBtn" type="button" aria-label="Export logs">
		<svg class="audit-export-btn__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
			<path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15" stroke="white" stroke-width="2" stroke-linecap="round"/>
			<path d="M7 10L12 15L17 10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			<path d="M12 15V3" stroke="white" stroke-width="2" stroke-linecap="round"/>
		</svg>
		Export Logs
	</button>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page' => 'audit-logs',
	'auditLogs' => [
		'api' => [
			'listUrl' => $auditApi['listUrl'] ?? '',
			'exportUrl' => $auditApi['exportUrl'] ?? '',
			'detailUrlTemplate' => $auditApi['detailUrlTemplate'] ?? '',
		],
		'defaults' => [
			'perPage' => 10,
		],
		'filters' => [
			'actions' => [
				['value' => '', 'label' => 'All Actions'],
			],
			'users' => [
				['value' => '', 'label' => 'All Users'],
			],
			'roles' => [
				['value' => '', 'label' => 'All Roles'],
			],
			'modules' => [
				['value' => '', 'label' => 'All Modules'],
			],
			'results' => [
				['value' => '', 'label' => 'All Results'],
			],
		],
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	<main class="main container-fluid px-0">
		<div class="page-body container-fluid py-3">
			<section class="audit-stats row g-3" aria-label="Audit statistics">
				<div class="col-6 col-xl-3">
					<article class="audit-stat-card audit-stat-card--green h-100">
						<p class="audit-stat-card__label">Total Logs</p>
						<p class="audit-stat-card__value" id="auditStatTotal">0</p>
					</article>
				</div>
				<div class="col-6 col-xl-3">
					<article class="audit-stat-card audit-stat-card--gold h-100">
						<p class="audit-stat-card__label">Success</p>
						<p class="audit-stat-card__value" id="auditStatSuccess">0</p>
					</article>
				</div>
				<div class="col-6 col-xl-3">
					<article class="audit-stat-card audit-stat-card--red h-100">
						<p class="audit-stat-card__label">Failed</p>
						<p class="audit-stat-card__value" id="auditStatFailed">0</p>
					</article>
				</div>
				<div class="col-6 col-xl-3">
					<article class="audit-stat-card audit-stat-card--blue h-100">
						<p class="audit-stat-card__label">Warnings</p>
						<p class="audit-stat-card__value" id="auditStatWarnings">0</p>
					</article>
				</div>
			</section>

			<section class="audit-filter-card row g-3 align-items-center" role="search" aria-label="Audit log filters">
				<div class="col-12 col-lg">
					<div class="audit-search-wrap">
						<span class="audit-search-wrap__icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
								<line x1="21" y1="21" x2="16.65" y2="16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							</svg>
						</span>
						<input type="text" class="form-control audit-search-input" id="auditSearchInput" placeholder="Search by date, user, or action..." aria-label="Search logs">
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-3">
					<div class="audit-select-wrap">
						<select class="form-select audit-filter-select" id="auditActionFilter" aria-label="Filter by action"></select>
						<span class="audit-select-wrap__chevron" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<polyline points="6 9 12 15 18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</span>
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-3">
					<div class="audit-select-wrap">
						<select class="form-select audit-filter-select" id="auditUserFilter" aria-label="Filter by user"></select>
						<span class="audit-select-wrap__chevron" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<polyline points="6 9 12 15 18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</span>
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-3">
					<input type="text" class="form-control audit-filter-select" id="auditActorInput" placeholder="Filter actor name..." aria-label="Filter by actor name">
				</div>

				<div class="col-12 col-md-6 col-xl-2">
					<select class="form-select audit-filter-select" id="auditRoleFilter" aria-label="Filter by actor role"></select>
				</div>

				<div class="col-12 col-md-6 col-xl-2">
					<select class="form-select audit-filter-select" id="auditModuleFilter" aria-label="Filter by module"></select>
				</div>

				<div class="col-12 col-md-6 col-xl-2">
					<select class="form-select audit-filter-select" id="auditResultFilter" aria-label="Filter by result"></select>
				</div>

				<div class="col-6 col-md-3 col-xl-2">
					<label class="small fw-semibold text-muted" for="auditStartDate">From</label>
					<input type="date" class="form-control" id="auditStartDate" aria-label="Audit start date">
				</div>
				<div class="col-6 col-md-3 col-xl-2">
					<label class="small fw-semibold text-muted" for="auditEndDate">To</label>
					<input type="date" class="form-control" id="auditEndDate" aria-label="Audit end date">
				</div>

				<div class="col-12 col-xl-3">
					<div class="audit-security-filter form-check form-switch">
						<input class="form-check-input me-2" type="checkbox" role="switch" id="auditSecurityPolicyFilter" aria-label="Filter global security policy changes only">
						<label class="form-check-label small fw-semibold" for="auditSecurityPolicyFilter">Global Security Policy Changes Only</label>
					</div>
				</div>
			</section>

			<section class="audit-table-card" aria-label="Activity logs">
				<div class="audit-table-card__header d-flex align-items-center justify-content-between gap-3">
					<h2 class="audit-table-card__title mb-0">Activity Logs</h2>
					<p class="audit-table-card__meta mb-0" id="auditTableMeta">Showing 0 of 0 entries</p>
				</div>

				<div class="table-responsive">
					<table class="table audit-table align-middle mb-0" aria-label="Audit activity logs table">
						<thead>
							<tr>
								<th scope="col">Date &amp; Time</th>
								<th scope="col">User</th>
								<th scope="col">Action</th>
								<th scope="col" class="audit-col-module">Module</th>
								<th scope="col" class="audit-col-ip">IP Address</th>
								<th scope="col">Result</th>
								<th scope="col">Actions</th>
							</tr>
						</thead>
						<tbody id="auditTableBody"></tbody>
					</table>
				</div>

				<div class="admin-pagination admin-pagination--js audit-pagination-wrap mt-3" aria-label="Table pagination">
					<span class="admin-pagination__info" id="auditPaginationInfo">Showing 0 to 0 of 0 entries</span>
					<nav class="admin-pagination__links" id="auditPagination" aria-label="Pagination links"></nav>
				</div>
			</section>
		</div>
	</main>

	<div class="modal fade" id="auditDetailModal" tabindex="-1" aria-labelledby="auditDetailTitle" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h2 class="modal-title fs-5" id="auditDetailTitle">Audit Log Details</h2>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="auditDetailBody"><div class="text-center text-muted py-4">Loading details…</div></div>
				<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
			</div>
		</div>
	</div>
@endsection

@push('admin_scripts')
<script>
	(function () {
		var payload = window.AdminPageData && window.AdminPageData.auditLogs ? window.AdminPageData.auditLogs : {};
		var api = payload.api && typeof payload.api === 'object' ? payload.api : {};
		var defaults = payload.defaults && typeof payload.defaults === 'object' ? payload.defaults : {};

		var listUrl = String(api.listUrl || '');
		var exportUrl = String(api.exportUrl || '');
		var detailUrlTemplate = String(api.detailUrlTemplate || '');

		var state = {
			search: '',
			actor: '',
			actorRole: '',
			actionType: '',
			moduleType: '',
			result: '',
			userType: '',
			securityPolicyOnly: false,
			startDate: '',
			endDate: '',
			page: 1,
			perPage: Math.max(1, Number(defaults.perPage || 10)),
			total: 0,
			lastPage: 1,
			isLoading: false,
			errorMessage: ''
		};

		var entries = [];
		var stats = {
			total: 0,
			success: 0,
			failed: 0,
			warning: 0
		};

		var filtersCache = {
			actions: Array.isArray(payload.filters && payload.filters.actions) ? payload.filters.actions : [{ value: '', label: 'All Actions' }],
			users: Array.isArray(payload.filters && payload.filters.users) ? payload.filters.users : [{ value: '', label: 'All Users' }],
			roles: Array.isArray(payload.filters && payload.filters.roles) ? payload.filters.roles : [{ value: '', label: 'All Roles' }],
			modules: Array.isArray(payload.filters && payload.filters.modules) ? payload.filters.modules : [{ value: '', label: 'All Modules' }],
			results: Array.isArray(payload.filters && payload.filters.results) ? payload.filters.results : [{ value: '', label: 'All Results' }]
		};

		var searchDebounceHandle = null;

		var searchInput = document.getElementById('auditSearchInput');
		var actorInput = document.getElementById('auditActorInput');
		var actionFilter = document.getElementById('auditActionFilter');
		var userFilter = document.getElementById('auditUserFilter');
		var roleFilter = document.getElementById('auditRoleFilter');
		var moduleFilter = document.getElementById('auditModuleFilter');
		var resultFilter = document.getElementById('auditResultFilter');
		var startDateInput = document.getElementById('auditStartDate');
		var endDateInput = document.getElementById('auditEndDate');
		var securityPolicyFilter = document.getElementById('auditSecurityPolicyFilter');
		var exportBtn = document.getElementById('auditExportBtn');
		var tableBody = document.getElementById('auditTableBody');
		var tableMeta = document.getElementById('auditTableMeta');
		var pagination = document.getElementById('auditPagination');
		var paginationInfo = document.getElementById('auditPaginationInfo');

		var statTotal = document.getElementById('auditStatTotal');
		var statSuccess = document.getElementById('auditStatSuccess');
		var statFailed = document.getElementById('auditStatFailed');
		var statWarnings = document.getElementById('auditStatWarnings');
		var detailBody = document.getElementById('auditDetailBody');
		var detailModal = document.getElementById('auditDetailModal');

		function escapeHtml(value) {
			return String(value || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		}

		function classToken(value) {
			return String(value || '')
				.toLowerCase()
				.replace(/[^a-z0-9_-]/g, '-');
		}

		function populateSelect(selectElement, options, fallbackLabel, currentValue) {
			if (!selectElement) {
				return;
			}

			var list = Array.isArray(options) && options.length
				? options
				: [{ value: '', label: fallbackLabel }];

			selectElement.innerHTML = list.map(function (option) {
				return '<option value="' + escapeHtml(option.value) + '">' + escapeHtml(option.label) + '</option>';
			}).join('');

			if (typeof currentValue !== 'undefined') {
				selectElement.value = String(currentValue);
			}
		}

		function getUserAvatarIcon(userType) {
			if (userType === 'system') {
				return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 15.5A3.5 3.5 0 0 1 8.5 12 3.5 3.5 0 0 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5 3.5 3.5 0 0 1-3.5 3.5m7.43-2.92c.04-.33.07-.67.07-1.08s-.03-.74-.07-1.08l2.32-1.82c.21-.16.27-.45.13-.69l-2.2-3.8c-.13-.23-.43-.31-.67-.23l-2.73 1.1c-.57-.43-1.17-.8-1.84-1.07L14.5 2.42c-.04-.26-.27-.42-.5-.42h-4c-.23 0-.46.16-.5.42l-.41 2.9c-.66.27-1.27.63-1.84 1.07L4.52 5.3c-.24-.09-.54 0-.67.23l-2.2 3.8c-.14.24-.07.53.13.69l2.32 1.82c-.04.34-.07.67-.07 1.08s.03.74.07 1.08l-2.32 1.82c-.21.16-.27.45-.13.69l2.2 3.8c.13.23.43.31.67.23l2.73-1.1c.57.43 1.17.8 1.84 1.07l.41 2.9c.04.26.27.42.5.42h4c.23 0 .46-.16.5-.42l.41-2.9c.66-.27 1.27-.63 1.84-1.07l2.73 1.1c.24.09.54 0 .67-.23l2.2-3.8c.14-.24.07-.53-.13-.69l-2.32-1.82z"/></svg>';
			}

			return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>';
		}

		function getResultIcon(result) {
			if (result === 'failed') {
				return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
			}
			if (result === 'warning') {
				return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2L2 20h20L12 2z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1"/></svg>';
			}

			return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
		}

		function renderStats() {
			if (statTotal) {
				statTotal.textContent = String(Math.max(0, Number(stats.total || 0)));
			}
			if (statSuccess) {
				statSuccess.textContent = String(Math.max(0, Number(stats.success || 0)));
			}
			if (statFailed) {
				statFailed.textContent = String(Math.max(0, Number(stats.failed || 0)));
			}
			if (statWarnings) {
				statWarnings.textContent = String(Math.max(0, Number(stats.warning || 0)));
			}
		}

		function renderPagination() {
			if (!pagination) {
				return;
			}

			if (window.eDonateAdminPagination) {
				window.eDonateAdminPagination.render(pagination, {
					current_page: state.page,
					last_page: state.lastPage,
					per_page: state.perPage,
					total: state.total,
					from: state.total > 0 ? ((state.page - 1) * state.perPage) + 1 : 0,
					to: state.total > 0 ? Math.min(state.total, ((state.page - 1) * state.perPage) + entries.length) : 0
				}, fetchLogs, { infoElement: paginationInfo });
			}
		}

		function renderRows() {
			if (!tableBody) {
				return;
			}

			if (state.isLoading) {
				tableBody.innerHTML = '<tr><td colspan="7" class="audit-table__empty">Loading log entries...</td></tr>';
				if (tableMeta) {
					tableMeta.textContent = 'Loading entries...';
				}
				return;
			}

			if (state.errorMessage) {
				tableBody.innerHTML = '<tr><td colspan="7" class="audit-table__empty">' + escapeHtml(state.errorMessage) + '</td></tr>';
				if (tableMeta) {
					tableMeta.textContent = 'Showing 0 of 0 entries';
				}
				return;
			}

			if (!entries.length) {
				tableBody.innerHTML = '<tr><td colspan="7" class="audit-table__empty">No log entries found.</td></tr>';
				if (tableMeta) {
					tableMeta.textContent = 'Showing 0 of ' + state.total + ' entries';
				}
				return;
			}

			tableBody.innerHTML = entries.map(function (entry) {
				var userType = classToken(entry.userType);
				var actionType = classToken(entry.actionType);
				var moduleType = classToken(entry.moduleType);
				var resultType = classToken(entry.result);

				var userAvatarClass = 'audit-user__avatar--admin';
				if (userType === 'donor') {
					userAvatarClass = 'audit-user__avatar--donor';
				} else if (userType === 'system') {
					userAvatarClass = 'audit-user__avatar--system';
				}

				return '<tr>' +
					'<td><div class="audit-datetime">' +
					'<span class="audit-datetime__icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>' +
					'<span class="audit-datetime__text">' + escapeHtml(entry.timestamp) + '</span>' +
					'</div></td>' +
					'<td><div class="audit-user">' +
					'<span class="audit-user__avatar ' + userAvatarClass + '" aria-hidden="true">' + getUserAvatarIcon(userType) + '</span>' +
					'<span class="audit-user__meta"><span class="audit-user__name">' + escapeHtml(entry.userName) + '</span><span class="audit-user__role">' + escapeHtml(entry.userRole) + '</span></span>' +
					'</div></td>' +
					'<td><div class="audit-action"><span class="audit-action__desc">' + escapeHtml(entry.description) + '</span>' +
					'<span class="audit-action-badge audit-action-badge--' + actionType + '">' + escapeHtml(entry.actionLabel) + '</span></div></td>' +
					'<td class="audit-col-module"><span class="audit-module-chip audit-module-chip--' + moduleType + '">' + escapeHtml(entry.moduleLabel) + '</span></td>' +
					'<td class="audit-col-ip"><span class="audit-ip-text">' + escapeHtml(entry.ipAddress) + '</span></td>' +
					'<td><span class="audit-result-pill audit-result-pill--' + resultType + '">' + getResultIcon(resultType) + escapeHtml(entry.resultLabel) + '</span></td>' +
					'<td><button class="audit-view-link btn btn-link p-0" type="button" data-audit-id="' + escapeHtml(entry.id) + '" aria-label="View log details for ' + escapeHtml(entry.userName) + '"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>View</button></td>' +
					'</tr>';
			}).join('');

			if (tableMeta) {
				var start = state.total === 0 ? 0 : ((state.page - 1) * state.perPage + 1);
				var end = state.total === 0 ? 0 : (start + entries.length - 1);
				tableMeta.textContent = 'Showing ' + start + ' to ' + end + ' of ' + state.total + ' entries';
			}
		}

		function buildQueryParams(includePagination) {
			var params = new URLSearchParams();

			if (state.search) {
				params.set('search', state.search);
			}
			if (state.actor) {
				params.set('actor', state.actor);
			}
			if (state.actorRole) {
				params.set('actor_role', state.actorRole);
			}
			if (state.actionType) {
				params.set('action_type', state.actionType);
			}
			if (state.moduleType) {
				params.set('module_type', state.moduleType);
			}
			if (state.result) {
				params.set('result', state.result);
			}
			if (state.userType) {
				params.set('user_type', state.userType);
			}
			if (state.startDate) {
				params.set('start_date', state.startDate);
			}
			if (state.endDate) {
				params.set('end_date', state.endDate);
			}
			if (state.securityPolicyOnly) {
				params.set('security_policy_only', '1');
			}

			if (includePagination) {
				params.set('page', String(state.page));
				params.set('per_page', String(state.perPage));
			}

			return params;
		}

		function fetchLogs(page) {
			if (!listUrl) {
				state.errorMessage = 'Audit logs API endpoint is not configured.';
				state.isLoading = false;
				entries = [];
				renderStats();
				renderRows();
				renderPagination();
				return;
			}

			state.page = Math.max(1, Number(page || state.page || 1));
			state.isLoading = true;
			state.errorMessage = '';
			renderRows();

			var query = buildQueryParams(true).toString();
			var requestUrl = listUrl + (query ? ('?' + query) : '');

			fetch(requestUrl, {
				method: 'GET',
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				},
				credentials: 'same-origin'
			})
				.then(function (response) {
					return response.json().catch(function () {
						return {};
					}).then(function (responsePayload) {
						if (!response.ok) {
							throw new Error(responsePayload.message || 'Unable to load audit logs.');
						}
						return responsePayload;
					});
				})
				.then(function (responsePayload) {
					entries = Array.isArray(responsePayload.data) ? responsePayload.data : [];

					var meta = responsePayload.meta && typeof responsePayload.meta === 'object' ? responsePayload.meta : {};
					state.page = Math.max(1, Number(meta.current_page || state.page));
					state.lastPage = Math.max(1, Number(meta.last_page || 1));
					state.perPage = Math.max(1, Number(meta.per_page || state.perPage));
					state.total = Math.max(0, Number(meta.total || entries.length));

					stats = responsePayload.stats && typeof responsePayload.stats === 'object'
						? responsePayload.stats
						: { total: state.total, success: 0, failed: 0, warning: 0 };

					if (responsePayload.filters && typeof responsePayload.filters === 'object') {
						filtersCache.actions = Array.isArray(responsePayload.filters.actions) && responsePayload.filters.actions.length
							? responsePayload.filters.actions
							: filtersCache.actions;
						filtersCache.users = Array.isArray(responsePayload.filters.users) && responsePayload.filters.users.length
							? responsePayload.filters.users
							: filtersCache.users;
						filtersCache.roles = Array.isArray(responsePayload.filters.roles) && responsePayload.filters.roles.length
							? responsePayload.filters.roles
							: filtersCache.roles;
						filtersCache.modules = Array.isArray(responsePayload.filters.modules) && responsePayload.filters.modules.length
							? responsePayload.filters.modules
							: filtersCache.modules;
						filtersCache.results = Array.isArray(responsePayload.filters.results) && responsePayload.filters.results.length
							? responsePayload.filters.results
							: filtersCache.results;
					}

					populateSelect(actionFilter, filtersCache.actions, 'All Actions', state.actionType);
					populateSelect(userFilter, filtersCache.users, 'All Users', state.userType);
					populateSelect(roleFilter, filtersCache.roles, 'All Roles', state.actorRole);
					populateSelect(moduleFilter, filtersCache.modules, 'All Modules', state.moduleType);
					populateSelect(resultFilter, filtersCache.results, 'All Results', state.result);

					state.isLoading = false;
					renderStats();
					renderRows();
					renderPagination();
				})
				.catch(function (error) {
					state.isLoading = false;
					entries = [];
					stats = { total: 0, success: 0, failed: 0, warning: 0 };
					state.errorMessage = error && error.message ? error.message : 'Unable to load audit logs.';
					renderStats();
					renderRows();
					renderPagination();
				});
		}

		populateSelect(actionFilter, filtersCache.actions, 'All Actions', state.actionType);
		populateSelect(userFilter, filtersCache.users, 'All Users', state.userType);
		populateSelect(roleFilter, filtersCache.roles, 'All Roles', state.actorRole);
		populateSelect(moduleFilter, filtersCache.modules, 'All Modules', state.moduleType);
		populateSelect(resultFilter, filtersCache.results, 'All Results', state.result);
		renderStats();
		renderRows();
		renderPagination();

		if (searchInput) {
			searchInput.addEventListener('input', function () {
				state.search = searchInput.value.trim();

				if (searchDebounceHandle) {
					window.clearTimeout(searchDebounceHandle);
				}

				searchDebounceHandle = window.setTimeout(function () {
					fetchLogs(1);
				}, 250);
			});
		}

		if (actorInput) {
			actorInput.addEventListener('input', function () {
				state.actor = actorInput.value.trim();
				if (searchDebounceHandle) window.clearTimeout(searchDebounceHandle);
				searchDebounceHandle = window.setTimeout(function () { fetchLogs(1); }, 250);
			});
		}

		if (actionFilter) {
			actionFilter.addEventListener('change', function () {
				state.actionType = actionFilter.value;
				fetchLogs(1);
			});
		}

		if (userFilter) {
			userFilter.addEventListener('change', function () {
				state.userType = userFilter.value;
				fetchLogs(1);
			});
		}

		if (roleFilter) {
			roleFilter.addEventListener('change', function () {
				state.actorRole = roleFilter.value;
				fetchLogs(1);
			});
		}

		if (moduleFilter) {
			moduleFilter.addEventListener('change', function () {
				state.moduleType = moduleFilter.value;
				fetchLogs(1);
			});
		}

		if (resultFilter) {
			resultFilter.addEventListener('change', function () {
				state.result = resultFilter.value;
				fetchLogs(1);
			});
		}

		[startDateInput, endDateInput].forEach(function (element) {
			if (!element) return;
			element.addEventListener('change', function () {
				state.startDate = startDateInput ? startDateInput.value : '';
				state.endDate = endDateInput ? endDateInput.value : '';
				if (state.startDate && state.endDate && state.endDate < state.startDate) {
					state.endDate = state.startDate;
					endDateInput.value = state.endDate;
				}
				fetchLogs(1);
			});
		});

		if (securityPolicyFilter) {
			securityPolicyFilter.checked = !!state.securityPolicyOnly;
			securityPolicyFilter.addEventListener('change', function () {
				state.securityPolicyOnly = !!securityPolicyFilter.checked;
				fetchLogs(1);
			});
		}

		if (exportBtn) {
			exportBtn.addEventListener('click', function () {
				if (!exportUrl) {
					return;
				}

				var query = buildQueryParams(false).toString();
				var targetUrl = exportUrl + (query ? ('?' + query) : '');
				window.location.href = targetUrl;
			});
		}

		if (tableBody) {
			tableBody.addEventListener('click', function (event) {
				var button = event.target.closest ? event.target.closest('[data-audit-id]') : null;
				if (!button || !detailUrlTemplate) return;
				var id = button.getAttribute('data-audit-id');
				var detailUrl = detailUrlTemplate.replace('__AUDIT_ID__', encodeURIComponent(id));
				if (detailBody) detailBody.innerHTML = '<div class="text-center text-muted py-4">Loading details…</div>';
				var modal = window.bootstrap && detailModal ? window.bootstrap.Modal.getOrCreateInstance(detailModal) : null;
				if (modal) modal.show();
				fetch(detailUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
					.then(function (response) { return response.json().then(function (body) { if (!response.ok) throw new Error(body.message || 'Unable to load audit details.'); return body; }); })
					.then(function (body) {
						var entry = body.data || {};
						if (!detailBody) return;
						detailBody.innerHTML = '<dl class="row mb-0">' +
							'<dt class="col-sm-4">Date &amp; time</dt><dd class="col-sm-8">' + escapeHtml(entry.timestamp) + '</dd>' +
							'<dt class="col-sm-4">Actor</dt><dd class="col-sm-8">' + escapeHtml(entry.userName) + ' (' + escapeHtml(entry.userRole) + ')</dd>' +
							'<dt class="col-sm-4">Action</dt><dd class="col-sm-8">' + escapeHtml(entry.actionLabel) + '</dd>' +
							'<dt class="col-sm-4">Module</dt><dd class="col-sm-8">' + escapeHtml(entry.moduleLabel) + '</dd>' +
							'<dt class="col-sm-4">Description</dt><dd class="col-sm-8">' + escapeHtml(entry.description) + '</dd>' +
							'<dt class="col-sm-4">Target</dt><dd class="col-sm-8">' + escapeHtml(entry.targetTable || '-') + (entry.targetId ? ' #' + escapeHtml(entry.targetId) : '') + '</dd>' +
							'<dt class="col-sm-4">Result</dt><dd class="col-sm-8">' + escapeHtml(entry.resultLabel) + '</dd>' +
							'<dt class="col-sm-4">Details</dt><dd class="col-sm-8"><pre class="small bg-body-tertiary border rounded p-2 mb-0 audit-detail-json">' + escapeHtml(entry.metadataText || 'No additional details.') + '</pre></dd>' +
							'</dl>';
					})
					.catch(function (error) { if (detailBody) detailBody.innerHTML = '<p class="text-danger mb-0">' + escapeHtml(error.message || 'Unable to load audit details.') + '</p>'; });
			});
		}

		fetchLogs(1);
	})();
</script>
@endpush
