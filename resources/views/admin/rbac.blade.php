@extends('layouts.app')

@section('title', 'eDonate - RBAC Management')
@section('admin_page_class', 'admin-rbac-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('render_default_hamburger', 'false')

@section('header_title', 'RBAC Management')
@section('header_subtitle', 'Manage roles, permissions, and user access assignments')

@section('header_slot')
	<button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
	</button>
@endsection

@section('header_actions')
	<button class="rbac-add-role-btn btn d-none" id="rbacHeaderAddRoleBtn" type="button" aria-label="Add new role">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
			<path d="M12 5V19M5 12H19" stroke="white" stroke-width="2" stroke-linecap="round"/>
		</svg>
		Add Role
	</button>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page' => 'rbac',
	'rbac' => [
		'roles' => [
			['id' => 1, 'name' => 'Admin', 'slug' => 'admin', 'description' => 'Manages donors, reports, and user access', 'createdAt' => '2026-01-12'],
			['id' => 2, 'name' => 'Staff', 'slug' => 'staff', 'description' => 'Operations and appointment support access', 'createdAt' => '2026-01-18'],
		],
		'permissions' => [
			['id' => 1, 'key' => 'users.view', 'name' => 'View Users', 'module' => 'User Management', 'description' => 'View user records'],
			['id' => 2, 'key' => 'users.create', 'name' => 'Create User', 'module' => 'User Management', 'description' => 'Create new users'],
			['id' => 3, 'key' => 'users.update', 'name' => 'Update User', 'module' => 'User Management', 'description' => 'Edit user records'],
			['id' => 4, 'key' => 'users.delete', 'name' => 'Delete User', 'module' => 'User Management', 'description' => 'Delete users'],
			['id' => 5, 'key' => 'appointments.manage', 'name' => 'Manage Appointments', 'module' => 'Appointments', 'description' => 'Approve and reschedule appointments'],
			['id' => 6, 'key' => 'donations.view', 'name' => 'View Donation Records', 'module' => 'Donation Records', 'description' => 'View donation entries'],
			['id' => 7, 'key' => 'reports.view', 'name' => 'View Reports', 'module' => 'Reports & Analytics', 'description' => 'Access reports dashboard'],
			['id' => 8, 'key' => 'reports.export', 'name' => 'Export Reports', 'module' => 'Reports & Analytics', 'description' => 'Download generated reports'],
			['id' => 9, 'key' => 'notifications.send', 'name' => 'Send Notifications', 'module' => 'Notification Center', 'description' => 'Send custom notifications'],
			['id' => 10, 'key' => 'notifications.view', 'name' => 'View Notifications', 'module' => 'Notification Center', 'description' => 'View all notification logs'],
			['id' => 11, 'key' => 'audit.view', 'name' => 'View Audit Logs', 'module' => 'Audit Logs', 'description' => 'Review activity logs'],
			['id' => 12, 'key' => 'audit.export', 'name' => 'Export Audit Logs', 'module' => 'Audit Logs', 'description' => 'Export audit log entries'],
			['id' => 13, 'key' => 'blood.map.view', 'name' => 'View Blood Map', 'module' => 'Blood Availability Mapping', 'description' => 'Access blood availability map'],
			['id' => 14, 'key' => 'rbac.manage', 'name' => 'Manage RBAC', 'module' => 'RBAC', 'description' => 'Manage roles and permissions'],
		],
		'users' => [
			['id' => 1, 'name' => 'Mark Dela Cruz', 'email' => 'mark.delacruz@edonate.local', 'roleIds' => [1]],
			['id' => 2, 'name' => 'Angelique Rivera', 'email' => 'angelique.rivera@edonate.local', 'roleIds' => [1]],
			['id' => 3, 'name' => 'John Santos', 'email' => 'john.santos@edonate.local', 'roleIds' => [2]],
			['id' => 4, 'name' => 'Lea Garcia', 'email' => 'lea.garcia@edonate.local', 'roleIds' => [2]],
			['id' => 5, 'name' => 'Nico Mendoza', 'email' => 'nico.mendoza@edonate.local', 'roleIds' => [2]],
			['id' => 6, 'name' => 'Shane Flores', 'email' => 'shane.flores@edonate.local', 'roleIds' => [2]],
			['id' => 7, 'name' => 'Paolo Reyes', 'email' => 'paolo.reyes@edonate.local', 'roleIds' => [1]],
			['id' => 8, 'name' => 'Ivy Lopez', 'email' => 'ivy.lopez@edonate.local', 'roleIds' => [2]],
		],
		'rolePermissions' => [
			'1' => [1,2,3,4,5,6,7,8,9,10,11,12,13,14],
			'2' => [1,5,6,7,9,10,13],
		],
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('content')
	<main class="main container-fluid px-0">
		<div class="rbac-body container-fluid py-3">
			<div id="rbacAlertHost" class="rbac-alert-host"></div>

			<section class="rbac-summary row g-3" aria-label="RBAC overview">
				<div class="col-12 col-sm-6 col-xl-3">
					<article class="rbac-summary-card rbac-summary-card--red h-100">
						<p class="rbac-summary-card__label">Roles</p>
						<p class="rbac-summary-card__value" id="rbacStatRoles">0</p>
					</article>
				</div>
				<div class="col-12 col-sm-6 col-xl-3">
					<article class="rbac-summary-card rbac-summary-card--blue h-100">
						<p class="rbac-summary-card__label">Permissions</p>
						<p class="rbac-summary-card__value" id="rbacStatPermissions">0</p>
					</article>
				</div>
				<div class="col-12 col-sm-6 col-xl-3">
					<article class="rbac-summary-card rbac-summary-card--green h-100">
						<p class="rbac-summary-card__label">Users Assigned</p>
						<p class="rbac-summary-card__value" id="rbacStatUsers">0</p>
					</article>
				</div>
				<div class="col-12 col-sm-6 col-xl-3">
					<article class="rbac-summary-card rbac-summary-card--gold h-100">
						<p class="rbac-summary-card__label">Total Assignments</p>
						<p class="rbac-summary-card__value" id="rbacStatAssignments">0</p>
					</article>
				</div>
			</section>

			<section class="rbac-card" aria-label="RBAC management tabs">
				<ul class="nav nav-tabs rbac-tabs" id="rbacMainTabs" role="tablist">
					<li class="nav-item" role="presentation">
						<button class="nav-link active" id="rbac-roles-tab" data-bs-toggle="tab" data-bs-target="#rbac-roles-pane" type="button" role="tab" aria-controls="rbac-roles-pane" aria-selected="true">Roles</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="rbac-permissions-tab" data-bs-toggle="tab" data-bs-target="#rbac-permissions-pane" type="button" role="tab" aria-controls="rbac-permissions-pane" aria-selected="false">Permissions</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="rbac-users-tab" data-bs-toggle="tab" data-bs-target="#rbac-users-pane" type="button" role="tab" aria-controls="rbac-users-pane" aria-selected="false">Users</button>
					</li>
				</ul>

				<div class="tab-content rbac-tab-content" id="rbacMainTabsContent">
					<div class="tab-pane fade show active" id="rbac-roles-pane" role="tabpanel" aria-labelledby="rbac-roles-tab" tabindex="0">
						<div class="d-flex flex-wrap align-items-center gap-2 justify-content-between mb-3">
							<div class="rbac-search-wrap flex-grow-1">
								<input type="text" class="form-control" id="rbacRolesSearchInput" placeholder="Search roles by name, slug, or description" aria-label="Search roles">
							</div>
							<button class="btn btn-danger rbac-btn-add-role d-none" id="rbacInlineAddRoleBtn" type="button">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
									<path d="M12 5V19M5 12H19" stroke="white" stroke-width="2" stroke-linecap="round"/>
								</svg>
								New Role
							</button>
						</div>

						<div class="table-responsive">
							<table class="table rbac-table align-middle mb-0" aria-label="Roles table">
								<thead>
									<tr>
										<th scope="col">Role</th>
										<th scope="col">Slug</th>
										<th scope="col">Description</th>
										<th scope="col" class="text-center">Permissions</th>
										<th scope="col" class="text-center">Users</th>
										<th scope="col">Created</th>
										<th scope="col">Actions</th>
									</tr>
								</thead>
								<tbody id="rbacRolesTableBody"></tbody>
							</table>
						</div>

						<div class="rbac-pagination-wrap">
							<ul class="pagination pagination-sm justify-content-end mb-0" id="rbacRolesPagination"></ul>
						</div>
					</div>

					<div class="tab-pane fade" id="rbac-permissions-pane" role="tabpanel" aria-labelledby="rbac-permissions-tab" tabindex="0">
						<div class="row g-3">
							<div class="col-12 col-lg-5">
								<section class="rbac-subcard h-100" aria-label="Assign permissions to role">
									<h3 class="rbac-subcard__title">Assign Permissions To Role</h3>

									<label class="form-label" for="rbacPermissionRoleSelect">Role</label>
									<select class="form-select rbac-role-select mb-3" id="rbacPermissionRoleSelect" aria-label="Select role for permission assignment"></select>

									<label class="form-label" for="rbacPermissionsSearchInput">Search Permission</label>
									<input type="text" class="form-control mb-3" id="rbacPermissionsSearchInput" placeholder="Search by name, key, or module" aria-label="Search permissions">

									<div class="rbac-permission-list" id="rbacPermissionCheckboxes"></div>

									<button class="btn btn-danger w-100 mt-3" id="rbacSavePermissionsBtn" type="button">Save Permission Assignment</button>
								</section>
							</div>

							<div class="col-12 col-lg-7">
								<section class="rbac-subcard h-100" aria-label="Permissions list">
									<h3 class="rbac-subcard__title">Permissions List</h3>

									<div class="table-responsive">
										<table class="table rbac-table align-middle mb-0" aria-label="Permissions table">
											<thead>
												<tr>
													<th scope="col">Permission</th>
													<th scope="col">Key</th>
													<th scope="col">Module</th>
													<th scope="col">Status</th>
												</tr>
											</thead>
											<tbody id="rbacPermissionsTableBody"></tbody>
										</table>
									</div>

									<div class="rbac-pagination-wrap">
										<ul class="pagination pagination-sm justify-content-end mb-0" id="rbacPermissionsPagination"></ul>
									</div>
								</section>
							</div>
						</div>
					</div>

					<div class="tab-pane fade" id="rbac-users-pane" role="tabpanel" aria-labelledby="rbac-users-tab" tabindex="0">
						<div class="d-flex flex-wrap align-items-center gap-2 justify-content-between mb-3">
							<div class="rbac-search-wrap flex-grow-1">
								<input type="text" class="form-control" id="rbacUsersSearchInput" placeholder="Search users by name, email, or role" aria-label="Search users">
							</div>
						</div>

						<div class="table-responsive">
							<table class="table rbac-table align-middle mb-0" aria-label="User role assignment table">
								<thead>
									<tr>
										<th scope="col">User</th>
										<th scope="col">Email</th>
										<th scope="col">Current Roles</th>
										<th scope="col">Assign Role</th>
										<th scope="col">Action</th>
									</tr>
								</thead>
								<tbody id="rbacUsersTableBody"></tbody>
							</table>
						</div>

						<div class="rbac-pagination-wrap">
							<ul class="pagination pagination-sm justify-content-end mb-0" id="rbacUsersPagination"></ul>
						</div>
					</div>
				</div>
			</section>
		</div>
	</main>

	<div class="modal fade" id="rbacRoleModal" tabindex="-1" aria-labelledby="rbacRoleModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="rbacRoleModalLabel">Add Role</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form id="rbacRoleForm" novalidate>
					<input type="hidden" id="rbacRoleIdInput">
					<div class="modal-body">
						<div class="mb-3">
							<label class="form-label" for="rbacRoleNameInput">Role Name</label>
							<input type="text" class="form-control" id="rbacRoleNameInput" placeholder="Example: Content Manager" required>
						</div>
						<div class="mb-3">
							<label class="form-label" for="rbacRoleSlugInput">Role Slug</label>
							<input type="text" class="form-control" id="rbacRoleSlugInput" placeholder="example-content-manager" required>
						</div>
						<div>
							<label class="form-label" for="rbacRoleDescriptionInput">Description</label>
							<textarea class="form-control" id="rbacRoleDescriptionInput" rows="3" placeholder="Short description of this role"></textarea>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-danger" id="rbacRoleSaveBtn">Save Role</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="rbacDeleteRoleModal" tabindex="-1" aria-labelledby="rbacDeleteRoleModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="rbacDeleteRoleModalLabel">Confirm Delete</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					Are you sure you want to delete this role? Users assigned to this role will lose it.
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="rbacConfirmDeleteRoleBtn">Delete Role</button>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('admin_scripts')
<script>
	(function () {
		var rbacData = (window.AdminPageData && window.AdminPageData.rbac) ? window.AdminPageData.rbac : {};

		var roles = Array.isArray(rbacData.roles) ? rbacData.roles.map(function (role) {
			return Object.assign({}, role);
		}) : [];
		var permissions = Array.isArray(rbacData.permissions) ? rbacData.permissions.map(function (permission) {
			return Object.assign({}, permission);
		}) : [];
		var users = Array.isArray(rbacData.users) ? rbacData.users.map(function (user) {
			var clone = Object.assign({}, user);
			clone.roleIds = Array.isArray(clone.roleIds) ? clone.roleIds.slice() : [];
			return clone;
		}) : [];

		var rolePermissions = (rbacData.rolePermissions && typeof rbacData.rolePermissions === 'object')
			? JSON.parse(JSON.stringify(rbacData.rolePermissions))
			: {};

		var state = {
			roleSearch: '',
			permissionSearch: '',
			userSearch: '',
			rolesPage: 1,
			permissionsPage: 1,
			usersPage: 1,
			selectedPermissionRoleId: '',
			roleToDeleteId: null,
		};

		var settings = {
			rolesPerPage: 5,
			permissionsPerPage: 6,
			usersPerPage: 6,
		};

		var fixedRoleConfig = {
			names: ['admin', 'staff'],
			ids: [1, 2],
		};

		roles = roles.filter(function (role) {
			return fixedRoleConfig.ids.indexOf(Number(role.id)) !== -1;
		});

		users = users.map(function (user) {
			var mappedRoleId = Number(Array.isArray(user.roleIds) && user.roleIds.length ? user.roleIds[0] : 0);
			if (mappedRoleId !== 1 && mappedRoleId !== 2) {
				mappedRoleId = 2;
			}
			user.roleIds = [mappedRoleId];
			return user;
		});

		var roleIdSeed = roles.reduce(function (maxValue, role) {
			return Math.max(maxValue, Number(role.id) || 0);
		}, 0) + 1;

		var roleModalElement = document.getElementById('rbacRoleModal');
		var deleteModalElement = document.getElementById('rbacDeleteRoleModal');
		var roleModal = roleModalElement ? bootstrap.Modal.getOrCreateInstance(roleModalElement) : null;
		var deleteModal = deleteModalElement ? bootstrap.Modal.getOrCreateInstance(deleteModalElement) : null;

		var alertHost = document.getElementById('rbacAlertHost');
		var roleSearchInput = document.getElementById('rbacRolesSearchInput');
		var permissionSearchInput = document.getElementById('rbacPermissionsSearchInput');
		var userSearchInput = document.getElementById('rbacUsersSearchInput');

		var addRoleButtons = [
			document.getElementById('rbacHeaderAddRoleBtn'),
			document.getElementById('rbacInlineAddRoleBtn'),
		];

		var rolesTableBody = document.getElementById('rbacRolesTableBody');
		var permissionsTableBody = document.getElementById('rbacPermissionsTableBody');
		var usersTableBody = document.getElementById('rbacUsersTableBody');

		var rolePagination = document.getElementById('rbacRolesPagination');
		var permissionPagination = document.getElementById('rbacPermissionsPagination');
		var userPagination = document.getElementById('rbacUsersPagination');

		var permissionRoleSelect = document.getElementById('rbacPermissionRoleSelect');
		var permissionCheckboxList = document.getElementById('rbacPermissionCheckboxes');
		var savePermissionsBtn = document.getElementById('rbacSavePermissionsBtn');

		var roleForm = document.getElementById('rbacRoleForm');
		var roleIdInput = document.getElementById('rbacRoleIdInput');
		var roleNameInput = document.getElementById('rbacRoleNameInput');
		var roleSlugInput = document.getElementById('rbacRoleSlugInput');
		var roleDescriptionInput = document.getElementById('rbacRoleDescriptionInput');
		var roleModalTitle = document.getElementById('rbacRoleModalLabel');
		var roleSaveButton = document.getElementById('rbacRoleSaveBtn');

		var deleteRoleBtn = document.getElementById('rbacConfirmDeleteRoleBtn');

		function escapeHtml(value) {
			return String(value || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		}

		function slugify(value) {
			return String(value || '')
				.toLowerCase()
				.trim()
				.replace(/[^a-z0-9\s-]/g, '')
				.replace(/\s+/g, '-')
				.replace(/-+/g, '-');
		}

		function showAlert(type, message) {
			if (!alertHost) {
				return;
			}

			var toneMap = {
				success: 'alert-success',
				danger: 'alert-danger',
				warning: 'alert-warning',
				info: 'alert-info',
			};

			var alertElement = document.createElement('div');
			alertElement.className = 'alert ' + (toneMap[type] || 'alert-info') + ' alert-dismissible fade show';
			alertElement.setAttribute('role', 'alert');
			alertElement.innerHTML = escapeHtml(message) + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';

			alertHost.innerHTML = '';
			alertHost.appendChild(alertElement);

			window.setTimeout(function () {
				if (alertElement && alertElement.parentNode) {
					bootstrap.Alert.getOrCreateInstance(alertElement).close();
				}
			}, 2600);
		}

		function normalizeRolePermissions() {
			var existingIds = roles.map(function (role) {
				return String(role.id);
			});

			for (var i = 0; i < existingIds.length; i += 1) {
				var roleId = existingIds[i];
				if (!Array.isArray(rolePermissions[roleId])) {
					rolePermissions[roleId] = [];
				}
			}

			Object.keys(rolePermissions).forEach(function (roleId) {
				if (existingIds.indexOf(String(roleId)) === -1) {
					delete rolePermissions[roleId];
				}
			});
		}

		function getRoleById(roleId) {
			var targetId = Number(roleId);
			for (var i = 0; i < roles.length; i += 1) {
				if (Number(roles[i].id) === targetId) {
					return roles[i];
				}
			}
			return null;
		}

		function getRoleNames(roleIds) {
			var names = [];
			for (var i = 0; i < roleIds.length; i += 1) {
				var role = getRoleById(roleIds[i]);
				if (role) {
					names.push(role.name);
				}
			}
			return names;
		}

		function getUsersCountForRole(roleId) {
			var target = Number(roleId);
			var count = 0;
			for (var i = 0; i < users.length; i += 1) {
				var roleIds = Array.isArray(users[i].roleIds) ? users[i].roleIds : [];
				if (roleIds.some(function (id) { return Number(id) === target; })) {
					count += 1;
				}
			}
			return count;
		}

		function getPermissionsCountForRole(roleId) {
			var values = rolePermissions[String(roleId)];
			return Array.isArray(values) ? values.length : 0;
		}

		function isFixedRoleId(roleId) {
			return fixedRoleConfig.ids.indexOf(Number(roleId)) !== -1;
		}

		function paginate(items, currentPage, perPage) {
			var total = items.length;
			var totalPages = Math.max(1, Math.ceil(total / perPage));
			var page = Math.min(Math.max(1, currentPage), totalPages);
			var start = (page - 1) * perPage;

			return {
				items: items.slice(start, start + perPage),
				total: total,
				totalPages: totalPages,
				page: page,
			};
		}

		function renderPagination(container, currentPage, totalPages, onSelect) {
			if (!container) {
				return;
			}

			container.innerHTML = '';
			if (totalPages <= 1) {
				return;
			}

			function buildButton(label, targetPage, disabled, active) {
				var li = document.createElement('li');
				li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');

				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'page-link';
				button.textContent = label;
				button.disabled = disabled;
				button.dataset.page = String(targetPage);

				button.addEventListener('click', function () {
					if (!disabled) {
						onSelect(targetPage);
					}
				});

				li.appendChild(button);
				container.appendChild(li);
			}

			buildButton('Prev', currentPage - 1, currentPage === 1, false);
			for (var page = 1; page <= totalPages; page += 1) {
				buildButton(String(page), page, false, page === currentPage);
			}
			buildButton('Next', currentPage + 1, currentPage === totalPages, false);
		}

		function renderSummary() {
			var rolesCountElement = document.getElementById('rbacStatRoles');
			var permissionsCountElement = document.getElementById('rbacStatPermissions');
			var usersCountElement = document.getElementById('rbacStatUsers');
			var assignmentCountElement = document.getElementById('rbacStatAssignments');

			var assignmentCount = users.reduce(function (sum, user) {
				return sum + (Array.isArray(user.roleIds) ? user.roleIds.length : 0);
			}, 0);

			if (rolesCountElement) {
				rolesCountElement.textContent = String(roles.length);
			}
			if (permissionsCountElement) {
				permissionsCountElement.textContent = String(permissions.length);
			}
			if (usersCountElement) {
				usersCountElement.textContent = String(users.length);
			}
			if (assignmentCountElement) {
				assignmentCountElement.textContent = String(assignmentCount);
			}
		}

		function getFilteredRoles() {
			var term = state.roleSearch.toLowerCase();
			if (!term) {
				return roles.slice();
			}

			return roles.filter(function (role) {
				var text = [role.name, role.slug, role.description, role.createdAt].join(' ').toLowerCase();
				return text.indexOf(term) !== -1;
			});
		}

		function renderRoles() {
			if (!rolesTableBody) {
				return;
			}

			var filtered = getFilteredRoles();
			var paged = paginate(filtered, state.rolesPage, settings.rolesPerPage);
			state.rolesPage = paged.page;

			if (!paged.items.length) {
				rolesTableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No roles found.</td></tr>';
			} else {
				rolesTableBody.innerHTML = paged.items.map(function (role) {
					var actionMarkup = '<span class="text-muted small">Fixed</span>';

					if (!isFixedRoleId(role.id)) {
						actionMarkup = '<div class="d-flex flex-wrap gap-1">' +
							'<button class="btn btn-sm btn-outline-primary" type="button" data-role-action="edit" data-role-id="' + role.id + '">Edit</button>' +
							'<button class="btn btn-sm btn-outline-danger" type="button" data-role-action="delete" data-role-id="' + role.id + '">Delete</button>' +
							'</div>';
					}

					return '<tr>' +
						'<td><span class="rbac-role-pill">' + escapeHtml(role.name) + '</span></td>' +
						'<td><code class="rbac-code">' + escapeHtml(role.slug) + '</code></td>' +
						'<td>' + escapeHtml(role.description || '-') + '</td>' +
						'<td class="text-center"><span class="badge text-bg-light">' + getPermissionsCountForRole(role.id) + '</span></td>' +
						'<td class="text-center"><span class="badge text-bg-light">' + getUsersCountForRole(role.id) + '</span></td>' +
						'<td>' + escapeHtml(role.createdAt || '-') + '</td>' +
						'<td>' + actionMarkup + '</td>' +
						'</tr>';
				}).join('');
			}

			renderPagination(rolePagination, paged.page, paged.totalPages, function (nextPage) {
				state.rolesPage = nextPage;
				renderRoles();
			});
		}

		function renderPermissionRoleSelect() {
			if (!permissionRoleSelect) {
				return;
			}

			permissionRoleSelect.innerHTML = roles.map(function (role) {
				return '<option value="' + role.id + '">' + escapeHtml(role.name) + '</option>';
			}).join('');

			if (!roles.length) {
				state.selectedPermissionRoleId = '';
				permissionRoleSelect.innerHTML = '<option value="">No roles available</option>';
				permissionRoleSelect.disabled = true;
				return;
			}

			permissionRoleSelect.disabled = false;

			var hasSelectedRole = roles.some(function (role) {
				return String(role.id) === String(state.selectedPermissionRoleId);
			});

			if (!hasSelectedRole) {
				state.selectedPermissionRoleId = String(roles[0].id);
			}

			permissionRoleSelect.value = String(state.selectedPermissionRoleId);
		}

		function getFilteredPermissions() {
			var term = state.permissionSearch.toLowerCase();
			if (!term) {
				return permissions.slice();
			}

			return permissions.filter(function (permission) {
				var text = [permission.name, permission.key, permission.module, permission.description].join(' ').toLowerCase();
				return text.indexOf(term) !== -1;
			});
		}

		function renderPermissionCheckboxes() {
			if (!permissionCheckboxList) {
				return;
			}

			if (!roles.length || !state.selectedPermissionRoleId) {
				permissionCheckboxList.innerHTML = '<p class="text-muted mb-0">Create a role first to assign permissions.</p>';
				return;
			}

			var filtered = getFilteredPermissions();
			var selected = rolePermissions[String(state.selectedPermissionRoleId)] || [];

			if (!filtered.length) {
				permissionCheckboxList.innerHTML = '<p class="text-muted mb-0">No permissions match your search.</p>';
				return;
			}

			permissionCheckboxList.innerHTML = filtered.map(function (permission) {
				var checked = selected.indexOf(permission.id) !== -1 ? ' checked' : '';
				return '<label class="rbac-permission-item">' +
					'<input class="form-check-input" type="checkbox" value="' + permission.id + '"' + checked + '>' +
					'<span class="rbac-permission-item__text">' +
					'<span class="rbac-permission-item__name">' + escapeHtml(permission.name) + '</span>' +
					'<span class="rbac-permission-item__meta">' + escapeHtml(permission.module) + ' - ' + escapeHtml(permission.key) + '</span>' +
					'</span>' +
					'</label>';
			}).join('');
		}

		function renderPermissionsTable() {
			if (!permissionsTableBody) {
				return;
			}

			var filtered = getFilteredPermissions();
			var paged = paginate(filtered, state.permissionsPage, settings.permissionsPerPage);
			state.permissionsPage = paged.page;
			var selectedPermissionIds = rolePermissions[String(state.selectedPermissionRoleId)] || [];

			if (!paged.items.length) {
				permissionsTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No permissions found.</td></tr>';
			} else {
				permissionsTableBody.innerHTML = paged.items.map(function (permission) {
					var assigned = selectedPermissionIds.indexOf(permission.id) !== -1;
					return '<tr>' +
						'<td>' + escapeHtml(permission.name) + '</td>' +
						'<td><code class="rbac-code">' + escapeHtml(permission.key) + '</code></td>' +
						'<td>' + escapeHtml(permission.module) + '</td>' +
						'<td>' + (assigned
							? '<span class="badge text-bg-success">Assigned</span>'
							: '<span class="badge text-bg-light">Not Assigned</span>') + '</td>' +
						'</tr>';
				}).join('');
			}

			renderPagination(permissionPagination, paged.page, paged.totalPages, function (nextPage) {
				state.permissionsPage = nextPage;
				renderPermissionsTable();
			});
		}

		function getFilteredUsers() {
			var term = state.userSearch.toLowerCase();
			if (!term) {
				return users.slice();
			}

			return users.filter(function (user) {
				var rolesText = getRoleNames(user.roleIds || []).join(' ');
				var text = [user.name, user.email, rolesText].join(' ').toLowerCase();
				return text.indexOf(term) !== -1;
			});
		}

		function getRoleBadges(roleIds) {
			var badges = getRoleNames(roleIds || []).map(function (name) {
				return '<span class="badge rounded-pill text-bg-danger-subtle border border-danger-subtle text-danger-emphasis me-1 mb-1">' + escapeHtml(name) + '</span>';
			});
			return badges.length ? badges.join('') : '<span class="text-muted small">No role</span>';
		}

		function getAssignRoleSelect(user) {
			var selectedRole = Array.isArray(user.roleIds) && user.roleIds.length ? String(user.roleIds[0]) : '';
			var options = '<option value="">No Role</option>' + roles.map(function (role) {
				var selected = String(role.id) === selectedRole ? ' selected' : '';
				return '<option value="' + role.id + '"' + selected + '>' + escapeHtml(role.name) + '</option>';
			}).join('');

			return '<select class="form-select form-select-sm rbac-user-role-select" data-user-id="' + user.id + '">' + options + '</select>';
		}

		function renderUsers() {
			if (!usersTableBody) {
				return;
			}

			var filtered = getFilteredUsers();
			var paged = paginate(filtered, state.usersPage, settings.usersPerPage);
			state.usersPage = paged.page;

			if (!paged.items.length) {
				usersTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No users found.</td></tr>';
			} else {
				usersTableBody.innerHTML = paged.items.map(function (user) {
					return '<tr>' +
						'<td><span class="fw-medium">' + escapeHtml(user.name) + '</span></td>' +
						'<td>' + escapeHtml(user.email) + '</td>' +
						'<td>' + getRoleBadges(user.roleIds) + '</td>' +
						'<td>' + getAssignRoleSelect(user) + '</td>' +
						'<td><button class="btn btn-sm btn-outline-danger" type="button" data-user-action="save" data-user-id="' + user.id + '">Save</button></td>' +
						'</tr>';
				}).join('');
			}

			renderPagination(userPagination, paged.page, paged.totalPages, function (nextPage) {
				state.usersPage = nextPage;
				renderUsers();
			});
		}

		function openAddRoleModal() {
			if (!roleModal) {
				return;
			}

			showAlert('info', 'Roles are limited to Admin and Staff only.');
			return;

			roleForm.reset();
			roleIdInput.value = '';
			roleModalTitle.textContent = 'Add Role';
			roleSaveButton.textContent = 'Save Role';
			roleModal.show();
		}

		function openEditRoleModal(roleId) {
			if (!roleModal) {
				return;
			}

			if (isFixedRoleId(roleId)) {
				showAlert('info', 'Admin and Staff roles are fixed and cannot be edited.');
				return;
			}

			var role = getRoleById(roleId);
			if (!role) {
				return;
			}

			roleIdInput.value = String(role.id);
			roleNameInput.value = role.name;
			roleSlugInput.value = role.slug;
			roleDescriptionInput.value = role.description || '';

			roleModalTitle.textContent = 'Edit Role';
			roleSaveButton.textContent = 'Update Role';
			roleModal.show();
		}

		function refreshRoleDependents() {
			normalizeRolePermissions();
			renderSummary();
			renderRoles();
			renderPermissionRoleSelect();
			renderPermissionCheckboxes();
			renderPermissionsTable();
			renderUsers();
		}

		addRoleButtons.forEach(function (button) {
			if (button) {
				button.addEventListener('click', openAddRoleModal);
			}
		});

		if (roleNameInput && roleSlugInput) {
			roleNameInput.addEventListener('input', function () {
				if (!roleSlugInput.value.trim() || roleSlugInput.dataset.autofill === 'true') {
					roleSlugInput.value = slugify(roleNameInput.value);
					roleSlugInput.dataset.autofill = 'true';
				}
			});

			roleSlugInput.addEventListener('input', function () {
				if (roleSlugInput.value.trim()) {
					roleSlugInput.dataset.autofill = 'false';
				}
			});
		}

		if (roleForm) {
			roleForm.addEventListener('submit', function (event) {
				event.preventDefault();

				var roleId = roleIdInput.value ? Number(roleIdInput.value) : null;
				var roleName = roleNameInput.value.trim();
				var roleSlug = slugify(roleSlugInput.value.trim() || roleName);
				var roleDescription = roleDescriptionInput.value.trim();

				if (!roleName) {
					showAlert('danger', 'Role name is required.');
					return;
				}

				if (!roleSlug) {
					showAlert('danger', 'Role slug is required.');
					return;
				}

				if (fixedRoleConfig.names.indexOf(roleName.toLowerCase()) === -1 || fixedRoleConfig.names.indexOf(roleSlug.toLowerCase()) === -1) {
					showAlert('warning', 'Only Admin and Staff roles are allowed.');
					return;
				}

				var duplicate = roles.some(function (role) {
					var sameName = role.name.toLowerCase() === roleName.toLowerCase();
					var sameSlug = role.slug.toLowerCase() === roleSlug.toLowerCase();
					var sameRole = roleId && Number(role.id) === roleId;
					return (sameName || sameSlug) && !sameRole;
				});

				if (duplicate) {
					showAlert('danger', 'Role name or slug already exists.');
					return;
				}

				if (roleId) {
					if (isFixedRoleId(roleId)) {
						showAlert('info', 'Admin and Staff roles are fixed and cannot be edited.');
						if (roleModal) {
							roleModal.hide();
						}
						return;
					}

					var currentRole = getRoleById(roleId);
					if (currentRole) {
						currentRole.name = roleName;
						currentRole.slug = roleSlug;
						currentRole.description = roleDescription;
					}
					showAlert('success', 'Role updated successfully.');
				} else {
					showAlert('warning', 'Only Admin and Staff roles are allowed.');
					if (roleModal) {
						roleModal.hide();
					}
					return;

					roles.push({
						id: roleIdSeed,
						name: roleName,
						slug: roleSlug,
						description: roleDescription || 'Custom role',
						createdAt: new Date().toISOString().slice(0, 10),
					});

					rolePermissions[String(roleIdSeed)] = [];
					roleIdSeed += 1;
					showAlert('success', 'Role created successfully.');
				}

				if (roleModal) {
					roleModal.hide();
				}

				refreshRoleDependents();
			});
		}

		if (rolesTableBody) {
			rolesTableBody.addEventListener('click', function (event) {
				var button = event.target.closest('button[data-role-action]');
				if (!button) {
					return;
				}

				var roleId = Number(button.getAttribute('data-role-id'));
				var action = button.getAttribute('data-role-action');

				if (action === 'edit') {
					openEditRoleModal(roleId);
					return;
				}

				if (action === 'delete') {
					state.roleToDeleteId = roleId;
					if (deleteModal) {
						deleteModal.show();
					}
				}
			});
		}

		if (deleteRoleBtn) {
			deleteRoleBtn.addEventListener('click', function () {
				if (!state.roleToDeleteId) {
					return;
				}

				if (isFixedRoleId(state.roleToDeleteId)) {
					showAlert('warning', 'Admin and Staff roles cannot be deleted.');
					state.roleToDeleteId = null;
					if (deleteModal) {
						deleteModal.hide();
					}
					return;
				}

				var roleId = Number(state.roleToDeleteId);
				var role = getRoleById(roleId);
				if (!role) {
					return;
				}

				roles = roles.filter(function (item) {
					return Number(item.id) !== roleId;
				});

				delete rolePermissions[String(roleId)];

				users.forEach(function (user) {
					var roleIds = Array.isArray(user.roleIds) ? user.roleIds : [];
					user.roleIds = roleIds.filter(function (id) {
						return Number(id) !== roleId;
					});
				});

				state.roleToDeleteId = null;
				if (deleteModal) {
					deleteModal.hide();
				}

				showAlert('warning', 'Role deleted successfully.');
				refreshRoleDependents();
			});
		}

		if (roleSearchInput) {
			roleSearchInput.addEventListener('input', function () {
				state.roleSearch = roleSearchInput.value.trim();
				state.rolesPage = 1;
				renderRoles();
			});
		}

		if (permissionSearchInput) {
			permissionSearchInput.addEventListener('input', function () {
				state.permissionSearch = permissionSearchInput.value.trim();
				state.permissionsPage = 1;
				renderPermissionCheckboxes();
				renderPermissionsTable();
			});
		}

		if (permissionRoleSelect) {
			permissionRoleSelect.addEventListener('change', function () {
				state.selectedPermissionRoleId = permissionRoleSelect.value;
				state.permissionsPage = 1;
				renderPermissionCheckboxes();
				renderPermissionsTable();
			});
		}

		if (savePermissionsBtn) {
			savePermissionsBtn.addEventListener('click', function () {
				if (!state.selectedPermissionRoleId) {
					showAlert('warning', 'Please select a role first.');
					return;
				}

				var checked = Array.from(permissionCheckboxList.querySelectorAll('input[type="checkbox"]:checked')).map(function (input) {
					return Number(input.value);
				});

				rolePermissions[String(state.selectedPermissionRoleId)] = checked;
				showAlert('success', 'Permissions updated successfully.');

				renderRoles();
				renderPermissionsTable();
			});
		}

		if (userSearchInput) {
			userSearchInput.addEventListener('input', function () {
				state.userSearch = userSearchInput.value.trim();
				state.usersPage = 1;
				renderUsers();
			});
		}

		if (usersTableBody) {
			usersTableBody.addEventListener('click', function (event) {
				var button = event.target.closest('button[data-user-action="save"]');
				if (!button) {
					return;
				}

				var userId = Number(button.getAttribute('data-user-id'));
				var select = usersTableBody.querySelector('select[data-user-id="' + userId + '"]');
				var selectedRole = select ? Number(select.value || 0) : 0;
				var user = users.find(function (item) { return Number(item.id) === userId; });

				if (!user) {
					return;
				}

				user.roleIds = selectedRole ? [selectedRole] : [];
				showAlert('success', 'User role assignment updated.');

				renderSummary();
				renderUsers();
				renderRoles();
			});
		}

		normalizeRolePermissions();
		renderSummary();
		renderRoles();
		renderPermissionRoleSelect();
		renderPermissionCheckboxes();
		renderPermissionsTable();
		renderUsers();
	})();
</script>
@endpush
