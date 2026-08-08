@extends('layouts.admin')

@section('title', 'eDonate - RBAC Management')
@section('admin_page_class', 'admin-rbac-page')
@section('header_title', 'RBAC Management')
@section('header_subtitle', 'Manage roles, permissions, and user access assignments')

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
		'users' => $rbacUsers ?? [],
		'rolePermissions' => [
			'1' => [1,2,3,4,5,6,7,8,9,10,11,12,13,14],
			'2' => [1,5,6,7,9,10,13],
		],
		'api' => $rbacApi ?? [],
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	<main class="main container-fluid px-0">
		<div class="rbac-body container-fluid py-3">
			<div id="rbacAlertHost" class="rbac-alert-host"></div>

			<section class="rbac-summary row g-3" aria-label="RBAC overview">
				<div class="col-6 col-xl-3">
					<article class="rbac-summary-card rbac-summary-card--red h-100">
						<p class="rbac-summary-card__label">Roles</p>
						<p class="rbac-summary-card__value" id="rbacStatRoles">0</p>
					</article>
				</div>
				<div class="col-6 col-xl-3">
					<article class="rbac-summary-card rbac-summary-card--blue h-100">
						<p class="rbac-summary-card__label">Permissions</p>
						<p class="rbac-summary-card__value" id="rbacStatPermissions">0</p>
					</article>
				</div>
				<div class="col-6 col-xl-3">
					<article class="rbac-summary-card rbac-summary-card--green h-100">
						<p class="rbac-summary-card__label">Users Assigned</p>
						<p class="rbac-summary-card__value" id="rbacStatUsers">0</p>
					</article>
				</div>
				<div class="col-6 col-xl-3">
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
							<div class="d-flex align-items-center gap-2">
								<select class="form-select form-select-sm" id="rbacUsersSortBySelect" aria-label="Sort admin users">
									<option value="created_at">Newest Created</option>
									<option value="name">Name</option>
									<option value="username">Username</option>
									<option value="email">Email</option>
									<option value="role">Role</option>
									<option value="admin_id">ID</option>
								</select>
								<button class="btn btn-outline-secondary btn-sm" id="rbacUsersSortDirBtn" type="button" data-dir="desc" aria-label="Toggle sort direction">Desc</button>
							</div>
							<button class="btn btn-danger" id="rbacAddUserBtn" type="button">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
									<path d="M12 5V19M5 12H19" stroke="white" stroke-width="2" stroke-linecap="round"/>
								</svg>
								Add Admin
							</button>
						</div>

						<div class="table-responsive">
							<table class="table rbac-table align-middle mb-0" aria-label="User role assignment table">
								<thead>
									<tr>
										<th scope="col">User</th>
										<th scope="col">Email</th>
										<th scope="col">Current Roles</th>
										<th scope="col">2FA Status</th>
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

	<div class="modal fade" id="rbacUserModal" tabindex="-1" aria-labelledby="rbacUserModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="rbacUserModalLabel">Add Admin User</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form id="rbacUserForm" novalidate>
					<input type="hidden" id="rbacUserIdInput">
					<div class="modal-body">
						<div class="mb-3">
							<label class="form-label" for="rbacUserFullNameInput">Full Name</label>
							<input type="text" class="form-control" id="rbacUserFullNameInput" placeholder="Example: Maria Santos">
						</div>
						<div class="mb-3">
							<label class="form-label" for="rbacUserUsernameInput">Username</label>
							<input type="text" class="form-control" id="rbacUserUsernameInput" placeholder="example.username" required>
						</div>
						<div class="mb-3">
							<label class="form-label" for="rbacUserEmailInput">Email</label>
							<input type="email" class="form-control" id="rbacUserEmailInput" placeholder="name@edonate.local" required>
						</div>
						<div class="mb-3">
							<label class="form-label" for="rbacUserRoleInput">Role</label>
							<select class="form-select" id="rbacUserRoleInput" required>
								<option value="1">Admin</option>
								<option value="2">Staff</option>
							</select>
						</div>
						<div id="rbacUserPasswordGroup">
							<label class="form-label" for="rbacUserPasswordInput">Password</label>
							<input type="password" class="form-control" id="rbacUserPasswordInput" minlength="8" placeholder="Minimum 8 characters">
							<div class="form-text" id="rbacUserPasswordHint">Required when creating a user.</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-danger" id="rbacUserSaveBtn">Save User</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="rbacDeleteUserModal" tabindex="-1" aria-labelledby="rbacDeleteUserModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="rbacDeleteUserModalLabel">Delete Admin User</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					This will permanently remove the selected admin account. Continue?
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="rbacConfirmDeleteUserBtn">Delete User</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="rbacResetPasswordModal" tabindex="-1" aria-labelledby="rbacResetPasswordModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="rbacResetPasswordModalLabel">Reset Admin Password</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form id="rbacResetPasswordForm" novalidate>
					<input type="hidden" id="rbacResetPasswordUserIdInput">
					<div class="modal-body">
						<div class="mb-3">
							<label class="form-label" for="rbacResetPasswordInput">New Password</label>
							<input type="password" class="form-control" id="rbacResetPasswordInput" minlength="8" placeholder="Minimum 8 characters" required>
						</div>
						<div>
							<label class="form-label" for="rbacResetPasswordConfirmInput">Confirm Password</label>
							<input type="password" class="form-control" id="rbacResetPasswordConfirmInput" minlength="8" placeholder="Re-enter password" required>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-danger" id="rbacResetPasswordSaveBtn">Reset Password</button>
					</div>
				</form>
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
			clone.id = Number(clone.id || 0);
			clone.fullName = String(clone.fullName || '');
			clone.username = String(clone.username || '');
			clone.name = String(clone.name || clone.fullName || clone.username || ('Admin #' + clone.id));
			clone.email = String(clone.email || '');
			clone.twoFactorEnrolled = !!clone.twoFactorEnrolled;
			clone.roleIds = Array.isArray(clone.roleIds) ? clone.roleIds.slice() : [];
			return clone;
		}) : [];

		var rolePermissions = (rbacData.rolePermissions && typeof rbacData.rolePermissions === 'object')
			? JSON.parse(JSON.stringify(rbacData.rolePermissions))
			: {};
		var rbacApi = (rbacData.api && typeof rbacData.api === 'object') ? rbacData.api : {};
		var listUsersUrl = String(rbacApi.listUsersUrl || '');
		var createUserUrl = String(rbacApi.createUserUrl || '');
		var updateUserUrlTemplate = String(rbacApi.updateUserUrlTemplate || '');
		var deleteUserUrlTemplate = String(rbacApi.deleteUserUrlTemplate || '');
		var resetUserPasswordUrlTemplate = String(rbacApi.resetUserPasswordUrlTemplate || '');
		var updateUserRoleUrlTemplate = String(rbacApi.updateUserRoleUrlTemplate || '');
		var currentAdminId = Number(rbacApi.currentAdminId || 0);
		var csrfToken = String(rbacApi.csrfToken || '');

		var state = {
			roleSearch: '',
			permissionSearch: '',
			userSearch: '',
			userSortBy: 'created_at',
			userSortDir: 'desc',
			rolesPage: 1,
			permissionsPage: 1,
			usersPage: 1,
			usersTotalPages: 1,
			usersLoading: false,
			selectedPermissionRoleId: '',
			roleToDeleteId: null,
			userToDeleteId: null,
			userToResetPasswordId: null,
		};

		var usersTotalCount = 0;
		var roleUserCounts = {
			'1': 0,
			'2': 0,
		};
		var usersFetchDebounceHandle = null;

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
		var userModalElement = document.getElementById('rbacUserModal');
		var deleteUserModalElement = document.getElementById('rbacDeleteUserModal');
		var resetPasswordModalElement = document.getElementById('rbacResetPasswordModal');
		var roleModal = roleModalElement ? bootstrap.Modal.getOrCreateInstance(roleModalElement) : null;
		var deleteModal = deleteModalElement ? bootstrap.Modal.getOrCreateInstance(deleteModalElement) : null;
		var userModal = userModalElement ? bootstrap.Modal.getOrCreateInstance(userModalElement) : null;
		var deleteUserModal = deleteUserModalElement ? bootstrap.Modal.getOrCreateInstance(deleteUserModalElement) : null;
		var resetPasswordModal = resetPasswordModalElement ? bootstrap.Modal.getOrCreateInstance(resetPasswordModalElement) : null;

		var alertHost = document.getElementById('rbacAlertHost');
		var roleSearchInput = document.getElementById('rbacRolesSearchInput');
		var permissionSearchInput = document.getElementById('rbacPermissionsSearchInput');
		var userSearchInput = document.getElementById('rbacUsersSearchInput');
		var userSortBySelect = document.getElementById('rbacUsersSortBySelect');
		var userSortDirBtn = document.getElementById('rbacUsersSortDirBtn');

		var addRoleButtons = [
			document.getElementById('rbacHeaderAddRoleBtn'),
			document.getElementById('rbacInlineAddRoleBtn'),
		];
		var addUserButton = document.getElementById('rbacAddUserBtn');

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
		var userForm = document.getElementById('rbacUserForm');
		var userIdInput = document.getElementById('rbacUserIdInput');
		var userFullNameInput = document.getElementById('rbacUserFullNameInput');
		var userUsernameInput = document.getElementById('rbacUserUsernameInput');
		var userEmailInput = document.getElementById('rbacUserEmailInput');
		var userRoleInput = document.getElementById('rbacUserRoleInput');
		var userPasswordGroup = document.getElementById('rbacUserPasswordGroup');
		var userPasswordInput = document.getElementById('rbacUserPasswordInput');
		var userPasswordHint = document.getElementById('rbacUserPasswordHint');
		var userModalTitle = document.getElementById('rbacUserModalLabel');
		var userSaveButton = document.getElementById('rbacUserSaveBtn');
		var deleteUserBtn = document.getElementById('rbacConfirmDeleteUserBtn');
		var resetPasswordForm = document.getElementById('rbacResetPasswordForm');
		var resetPasswordUserIdInput = document.getElementById('rbacResetPasswordUserIdInput');
		var resetPasswordInput = document.getElementById('rbacResetPasswordInput');
		var resetPasswordConfirmInput = document.getElementById('rbacResetPasswordConfirmInput');
		var resetPasswordSaveBtn = document.getElementById('rbacResetPasswordSaveBtn');

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

		function buildUpdateUserRoleUrl(userId) {
			if (!updateUserRoleUrlTemplate) {
				return '';
			}

			return updateUserRoleUrlTemplate.replace('__ADMIN_ID__', encodeURIComponent(String(userId)));
		}

		function buildUpdateUserUrl(userId) {
			if (!updateUserUrlTemplate) {
				return '';
			}

			return updateUserUrlTemplate.replace('__ADMIN_ID__', encodeURIComponent(String(userId)));
		}

		function buildDeleteUserUrl(userId) {
			if (!deleteUserUrlTemplate) {
				return '';
			}

			return deleteUserUrlTemplate.replace('__ADMIN_ID__', encodeURIComponent(String(userId)));
		}

		function buildResetUserPasswordUrl(userId) {
			if (!resetUserPasswordUrlTemplate) {
				return '';
			}

			return resetUserPasswordUrlTemplate.replace('__ADMIN_ID__', encodeURIComponent(String(userId)));
		}

		function normalizeRoleId(value) {
			return Number(value) === 1 ? 1 : 2;
		}

		function normalizeIncomingUser(userPayload) {
			var clone = Object.assign({}, userPayload || {});
			clone.id = Number(clone.id || 0);
			clone.fullName = String(clone.fullName || '');
			clone.username = String(clone.username || '');
			clone.name = String(clone.name || clone.fullName || clone.username || ('Admin #' + clone.id));
			clone.email = String(clone.email || '');
			clone.twoFactorEnrolled = !!clone.twoFactorEnrolled;
			clone.roleIds = [normalizeRoleId(Array.isArray(clone.roleIds) && clone.roleIds.length ? clone.roleIds[0] : 2)];

			return clone;
		}

		function getUserById(userId) {
			var targetId = Number(userId);
			for (var i = 0; i < users.length; i += 1) {
				if (Number(users[i].id) === targetId) {
					return users[i];
				}
			}

			return null;
		}

		function upsertUserLocalCache(userPayload) {
			if (!userPayload || typeof userPayload !== 'object') {
				return;
			}

			var preparedUser = normalizeIncomingUser(userPayload);

			var existingIndex = users.findIndex(function (user) {
				return Number(user.id) === preparedUser.id;
			});

			if (existingIndex === -1) {
				users.push(preparedUser);
				return;
			}

			users[existingIndex] = Object.assign({}, users[existingIndex], preparedUser);
		}

		function removeUserFromLocalCache(userId) {
			var targetId = Number(userId);
			users = users.filter(function (user) {
				return Number(user.id) !== targetId;
			});
		}

		function setUsersSortDirection(direction) {
			state.userSortDir = direction === 'asc' ? 'asc' : 'desc';

			if (userSortDirBtn) {
				userSortDirBtn.dataset.dir = state.userSortDir;
				userSortDirBtn.textContent = state.userSortDir === 'asc' ? 'Asc' : 'Desc';
			}
		}

		function fetchUsers(page) {
			if (!listUsersUrl) {
				showAlert('danger', 'RBAC users listing route is not configured.');
				return;
			}

			var targetPage = Math.max(1, Number(page || state.usersPage || 1));
			var params = new URLSearchParams();
			params.set('page', String(targetPage));
			params.set('per_page', String(settings.usersPerPage));
			params.set('sort_by', String(state.userSortBy || 'created_at'));
			params.set('sort_dir', String(state.userSortDir || 'desc'));
			if (state.userSearch) {
				params.set('search', state.userSearch);
			}

			state.usersLoading = true;
			renderUsers();

			fetch(listUsersUrl + '?' + params.toString(), {
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
					}).then(function (payload) {
						if (!response.ok) {
							throw new Error(payload.message || 'Unable to load admin users.');
						}

						return payload;
					});
				})
				.then(function (payload) {
					var meta = (payload.meta && typeof payload.meta === 'object') ? payload.meta : {};
					var summary = (payload.summary && typeof payload.summary === 'object') ? payload.summary : {};
					var summaryRoleCounts = (summary.role_user_counts && typeof summary.role_user_counts === 'object')
						? summary.role_user_counts
						: {};

					users = Array.isArray(payload.data)
						? payload.data.map(function (user) { return normalizeIncomingUser(user); })
						: [];

					state.usersPage = Math.max(1, Number(meta.current_page || targetPage));
					state.usersTotalPages = Math.max(1, Number(meta.last_page || 1));
					usersTotalCount = Math.max(0, Number(summary.total_users || meta.total || users.length));
					roleUserCounts = {
						'1': Math.max(0, Number(summaryRoleCounts['1'] || summaryRoleCounts[1] || 0)),
						'2': Math.max(0, Number(summaryRoleCounts['2'] || summaryRoleCounts[2] || 0)),
					};

					if (users.length === 0 && state.usersPage > state.usersTotalPages) {
						fetchUsers(state.usersTotalPages);
						return;
					}

					state.usersLoading = false;
					renderSummary();
					renderRoles();
					renderUsers();
				})
				.catch(function (error) {
					state.usersLoading = false;
					renderUsers();
					showAlert('danger', error.message || 'Unable to load admin users.');
				});
		}

		function openCreateUserModal() {
			if (!userModal || !userForm) {
				return;
			}

			userForm.reset();
			userIdInput.value = '';
			userModalTitle.textContent = 'Add Admin User';
			userSaveButton.textContent = 'Create User';
			if (userPasswordGroup) {
				userPasswordGroup.classList.remove('d-none');
			}
			userPasswordInput.required = true;
			userPasswordHint.textContent = 'Required when creating a user. Minimum 8 characters.';
			userRoleInput.value = '2';
			userModal.show();
		}

		function openEditUserModal(userId) {
			if (!userModal || !userForm) {
				return;
			}

			var user = getUserById(userId);
			if (!user) {
				showAlert('warning', 'Selected user was not found.');
				return;
			}

			userIdInput.value = String(user.id);
			userFullNameInput.value = String(user.fullName || '');
			userUsernameInput.value = String(user.username || '');
			userEmailInput.value = String(user.email || '');
			userRoleInput.value = String(normalizeRoleId(Array.isArray(user.roleIds) && user.roleIds.length ? user.roleIds[0] : 2));
			if (userPasswordGroup) {
				userPasswordGroup.classList.add('d-none');
			}
			userPasswordInput.value = '';
			userPasswordInput.required = false;
			userModalTitle.textContent = 'Edit Admin User';
			userSaveButton.textContent = 'Update User';
			userModal.show();
		}

		function openResetPasswordModal(userId) {
			if (!resetPasswordModal || !resetPasswordForm) {
				return;
			}

			var user = getUserById(userId);
			if (!user) {
				showAlert('warning', 'Selected user was not found.');
				return;
			}

			state.userToResetPasswordId = Number(user.id);
			resetPasswordForm.reset();
			resetPasswordUserIdInput.value = String(user.id);
			resetPasswordModal.show();
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
			return Math.max(0, Number(roleUserCounts[String(roleId)] || 0));
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

			var assignmentCount = usersTotalCount;

			if (rolesCountElement) {
				rolesCountElement.textContent = String(roles.length);
			}
			if (permissionsCountElement) {
				permissionsCountElement.textContent = String(permissions.length);
			}
			if (usersCountElement) {
				usersCountElement.textContent = String(usersTotalCount);
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
				var twoFactorText = user.twoFactorEnrolled ? '2fa enrolled enabled' : '2fa not enrolled disabled';
				var text = [user.name, user.fullName, user.username, user.email, rolesText, twoFactorText].join(' ').toLowerCase();
				return text.indexOf(term) !== -1;
			});
		}

		function getRoleBadges(roleIds) {
			var badges = getRoleNames(roleIds || []).map(function (name) {
				return '<span class="badge rounded-pill text-bg-danger-subtle border border-danger-subtle text-danger-emphasis me-1 mb-1">' + escapeHtml(name) + '</span>';
			});
			return badges.length ? badges.join('') : '<span class="text-muted small">No role</span>';
		}

		function getTwoFactorStatusBadge(user) {
			if (user && user.twoFactorEnrolled) {
				return '<span class="badge text-bg-success">Enrolled</span>';
			}

			return '<span class="badge text-bg-warning">Not Enrolled</span>';
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

			if (state.usersLoading) {
				usersTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading users...</td></tr>';
				if (userPagination) {
					userPagination.innerHTML = '';
				}
				return;
			}

			if (!users.length) {
				usersTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>';
			} else {
				usersTableBody.innerHTML = users.map(function (user) {
					var isCurrentAdmin = Number(user.id) === currentAdminId;
					var deleteButtonDisabled = isCurrentAdmin ? ' disabled' : '';
					var deleteButtonTitle = isCurrentAdmin ? ' title="You cannot delete your own logged-in account"' : '';

					return '<tr>' +
						'<td><span class="fw-medium">' + escapeHtml(user.name) + '</span><div class="small text-muted">@' + escapeHtml(user.username || '-') + '</div></td>' +
						'<td>' + escapeHtml(user.email) + '</td>' +
						'<td>' + getRoleBadges(user.roleIds) + '</td>' +
						'<td>' + getTwoFactorStatusBadge(user) + '</td>' +
						'<td>' + getAssignRoleSelect(user) + '</td>' +
						'<td><div class="d-flex flex-wrap gap-1">' +
						'<button class="btn btn-sm btn-outline-danger" type="button" data-user-action="save" data-user-id="' + user.id + '">Save Role</button>' +
						'<button class="btn btn-sm btn-outline-primary" type="button" data-user-action="edit" data-user-id="' + user.id + '">Edit</button>' +
						'<button class="btn btn-sm btn-outline-warning" type="button" data-user-action="reset-password" data-user-id="' + user.id + '">Reset Password</button>' +
						'<button class="btn btn-sm btn-outline-secondary" type="button" data-user-action="delete" data-user-id="' + user.id + '"' + deleteButtonDisabled + deleteButtonTitle + '>Delete</button>' +
						'</div></td>' +
						'</tr>';
				}).join('');
			}

			renderPagination(userPagination, state.usersPage, state.usersTotalPages, function (nextPage) {
				fetchUsers(nextPage);
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

				if (usersFetchDebounceHandle) {
					window.clearTimeout(usersFetchDebounceHandle);
				}

				usersFetchDebounceHandle = window.setTimeout(function () {
					fetchUsers(1);
				}, 250);
			});
		}

		if (userSortBySelect) {
			userSortBySelect.value = state.userSortBy;

			userSortBySelect.addEventListener('change', function () {
				state.userSortBy = String(userSortBySelect.value || 'created_at');
				fetchUsers(1);
			});
		}

		if (userSortDirBtn) {
			setUsersSortDirection(state.userSortDir);

			userSortDirBtn.addEventListener('click', function () {
				setUsersSortDirection(state.userSortDir === 'asc' ? 'desc' : 'asc');
				fetchUsers(1);
			});
		}

		if (addUserButton) {
			addUserButton.addEventListener('click', openCreateUserModal);
		}

		if (userForm) {
			userForm.addEventListener('submit', function (event) {
				event.preventDefault();

				var editingUserId = Number(userIdInput.value || 0);
				var isEditing = editingUserId > 0;
				var fullName = userFullNameInput.value.trim();
				var username = userUsernameInput.value.trim();
				var email = userEmailInput.value.trim();
				var password = userPasswordInput.value;
				var roleId = Number(userRoleInput.value || 0);

				if (!username) {
					showAlert('warning', 'Username is required.');
					return;
				}

				if (!email) {
					showAlert('warning', 'Email is required.');
					return;
				}

				if (roleId !== 1 && roleId !== 2) {
					showAlert('warning', 'Please select Admin or Staff role.');
					return;
				}

				if (!isEditing && String(password || '').trim().length < 8) {
					showAlert('warning', 'Password is required and must be at least 8 characters.');
					return;
				}

				var requestUrl = isEditing ? buildUpdateUserUrl(editingUserId) : createUserUrl;
				var requestMethod = isEditing ? 'PUT' : 'POST';
				if (!requestUrl) {
					showAlert('danger', 'RBAC user CRUD route is not configured.');
					return;
				}

				var payload = {
					full_name: fullName,
					username: username,
					email: email,
					role_id: roleId
				};

				if (!isEditing) {
					payload.password = String(password || '');
				}

				var originalButtonText = userSaveButton.textContent;
				userSaveButton.disabled = true;
				userSaveButton.textContent = isEditing ? 'Updating...' : 'Creating...';

				fetch(requestUrl, {
					method: requestMethod,
					headers: {
						'Accept': 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': csrfToken,
						'X-Requested-With': 'XMLHttpRequest'
					},
					credentials: 'same-origin',
					body: JSON.stringify(payload)
				})
					.then(function (response) {
						return response.json().catch(function () {
							return {};
						}).then(function (responsePayload) {
							if (!response.ok) {
								if (responsePayload.errors && typeof responsePayload.errors === 'object') {
									var firstErrorKey = Object.keys(responsePayload.errors)[0];
									if (firstErrorKey && Array.isArray(responsePayload.errors[firstErrorKey]) && responsePayload.errors[firstErrorKey][0]) {
										throw new Error(responsePayload.errors[firstErrorKey][0]);
									}
								}

								throw new Error(responsePayload.message || 'Unable to save admin user.');
							}

							return responsePayload;
						});
					})
					.then(function (responsePayload) {
						if (userModal) {
							userModal.hide();
						}

						showAlert('success', responsePayload.message || 'Admin user saved successfully.');
						fetchUsers(isEditing ? state.usersPage : 1);
					})
					.catch(function (error) {
						showAlert('danger', error.message || 'Unable to save admin user.');
					})
					.finally(function () {
						userSaveButton.disabled = false;
						userSaveButton.textContent = originalButtonText;
					});
			});
		}

		if (usersTableBody) {
			usersTableBody.addEventListener('click', function (event) {
				var button = event.target.closest('button[data-user-action]');
				if (!button) {
					return;
				}

				var action = String(button.getAttribute('data-user-action') || '');
				var userId = Number(button.getAttribute('data-user-id'));

				if (!userId) {
					return;
				}

				if (action === 'edit') {
					openEditUserModal(userId);
					return;
				}

				if (action === 'delete') {
					if (userId === currentAdminId) {
						showAlert('warning', 'You cannot delete your own logged-in account.');
						return;
					}

					state.userToDeleteId = userId;
					if (deleteUserModal) {
						deleteUserModal.show();
					}
					return;
				}

				if (action === 'reset-password') {
					openResetPasswordModal(userId);
					return;
				}

				if (action !== 'save') {
					return;
				}

				var select = usersTableBody.querySelector('select[data-user-id="' + userId + '"]');
				var selectedRole = select ? Number(select.value || 0) : 0;
				var user = users.find(function (item) { return Number(item.id) === userId; });

				if (!user) {
					return;
				}

				if (selectedRole !== 1 && selectedRole !== 2) {
					showAlert('warning', 'Please assign Admin or Staff role.');
					return;
				}

				var requestUrl = buildUpdateUserRoleUrl(userId);
				if (!requestUrl) {
					showAlert('danger', 'RBAC user role update route is not configured.');
					return;
				}

				var originalButtonText = button.textContent;
				button.disabled = true;
				button.textContent = 'Saving...';

				fetch(requestUrl, {
					method: 'PATCH',
					headers: {
						'Accept': 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': csrfToken,
						'X-Requested-With': 'XMLHttpRequest'
					},
					credentials: 'same-origin',
					body: JSON.stringify({
						role_id: selectedRole
					})
				})
					.then(function (response) {
						return response.json().catch(function () {
							return {};
						}).then(function (payload) {
							if (!response.ok) {
								throw new Error(payload.message || 'Unable to update admin user role.');
							}

							return payload;
						});
					})
					.then(function (payload) {
						showAlert('success', payload.message || 'Admin user role assignment updated.');
						fetchUsers(state.usersPage);
					})
					.catch(function (error) {
						showAlert('danger', error.message || 'Unable to update admin user role.');
					})
					.finally(function () {
						button.disabled = false;
						button.textContent = originalButtonText;
					});
			});
		}

		if (deleteUserBtn) {
			deleteUserBtn.addEventListener('click', function () {
				if (!state.userToDeleteId) {
					return;
				}

				var deletingUserId = Number(state.userToDeleteId);
				if (deletingUserId === currentAdminId) {
					showAlert('warning', 'You cannot delete your own logged-in account.');
					return;
				}

				var requestUrl = buildDeleteUserUrl(deletingUserId);
				if (!requestUrl) {
					showAlert('danger', 'RBAC delete user route is not configured.');
					return;
				}

				deleteUserBtn.disabled = true;

				fetch(requestUrl, {
					method: 'DELETE',
					headers: {
						'Accept': 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': csrfToken,
						'X-Requested-With': 'XMLHttpRequest'
					},
					credentials: 'same-origin'
				})
					.then(function (response) {
						return response.json().catch(function () {
							return {};
						}).then(function (payload) {
							if (!response.ok) {
								throw new Error(payload.message || 'Unable to delete admin user.');
							}

							return payload;
						});
					})
					.then(function (payload) {
						state.userToDeleteId = null;

						if (deleteUserModal) {
							deleteUserModal.hide();
						}

						showAlert('success', payload.message || 'Admin user deleted successfully.');
						fetchUsers(state.usersPage);
					})
					.catch(function (error) {
						showAlert('danger', error.message || 'Unable to delete admin user.');
					})
					.finally(function () {
						deleteUserBtn.disabled = false;
					});
			});
		}

		if (resetPasswordForm) {
			resetPasswordForm.addEventListener('submit', function (event) {
				event.preventDefault();

				var targetUserId = Number(resetPasswordUserIdInput.value || state.userToResetPasswordId || 0);
				var newPassword = String(resetPasswordInput.value || '');
				var confirmPassword = String(resetPasswordConfirmInput.value || '');

				if (!targetUserId) {
					showAlert('warning', 'Please select a valid admin user.');
					return;
				}

				if (newPassword.length < 8) {
					showAlert('warning', 'New password must be at least 8 characters.');
					return;
				}

				if (newPassword !== confirmPassword) {
					showAlert('warning', 'Password confirmation does not match.');
					return;
				}

				var requestUrl = buildResetUserPasswordUrl(targetUserId);
				if (!requestUrl) {
					showAlert('danger', 'RBAC reset password route is not configured.');
					return;
				}

				var originalButtonText = resetPasswordSaveBtn.textContent;
				resetPasswordSaveBtn.disabled = true;
				resetPasswordSaveBtn.textContent = 'Resetting...';

				fetch(requestUrl, {
					method: 'PATCH',
					headers: {
						'Accept': 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': csrfToken,
						'X-Requested-With': 'XMLHttpRequest'
					},
					credentials: 'same-origin',
					body: JSON.stringify({
						password: newPassword,
						password_confirmation: confirmPassword
					})
				})
					.then(function (response) {
						return response.json().catch(function () {
							return {};
						}).then(function (payload) {
							if (!response.ok) {
								if (payload.errors && typeof payload.errors === 'object') {
									var firstErrorKey = Object.keys(payload.errors)[0];
									if (firstErrorKey && Array.isArray(payload.errors[firstErrorKey]) && payload.errors[firstErrorKey][0]) {
										throw new Error(payload.errors[firstErrorKey][0]);
									}
								}

								throw new Error(payload.message || 'Unable to reset admin password.');
							}

							return payload;
						});
					})
					.then(function (payload) {
						state.userToResetPasswordId = null;
						if (resetPasswordModal) {
							resetPasswordModal.hide();
						}

						showAlert('success', payload.message || 'Admin password reset successfully.');
					})
					.catch(function (error) {
						showAlert('danger', error.message || 'Unable to reset admin password.');
					})
					.finally(function () {
						resetPasswordSaveBtn.disabled = false;
						resetPasswordSaveBtn.textContent = originalButtonText;
					});
			});
		}

		normalizeRolePermissions();
		setUsersSortDirection(state.userSortDir);
		renderRoles();
		renderPermissionRoleSelect();
		renderPermissionCheckboxes();
		renderPermissionsTable();
		renderSummary();
		fetchUsers(1);
	})();
</script>
@endpush
