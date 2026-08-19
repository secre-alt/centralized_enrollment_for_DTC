@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Manage Users')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:var(--dtc-text);">Manage Users</h4>
            <p class="mb-0" style="color:var(--dtc-text-secondary); font-size:13px;">
                Create and manage system user accounts
            </p>
        </div>
        <button type="button" class="btn btn-primary btn-sm add-user-btn" data-toggle="modal" data-target="#createUserModal">
            <i class="fas fa-user-plus mr-1"></i> 
            <span class="add-user-text">Add New User</span>
        </button>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

{{-- Stats Row --}}
<div class="row mb-2">
    @php
        $roleStatConfig = [
            'admin'         => ['icon' => 'fa-user-shield',    'color' => 'primary'],
            'registrar'     => ['icon' => 'fa-id-card',        'color' => 'success'],
            'cashier'       => ['icon' => 'fa-cash-register',  'color' => 'warning'],
            'student'       => ['icon' => 'fa-user-graduate',  'color' => 'info'],
            'alumni'        => ['icon' => 'fa-user-tie',       'color' => 'danger'],
            'new_applicant' => ['icon' => 'fa-user-clock',     'color' => 'neutral'],
        ];
    @endphp
    @foreach($roleStatConfig as $role => $cfg)
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <x-dtc.stat-card
            icon="{{ $cfg['icon'] }}" color="{{ $cfg['color'] }}"
            label="{{ str_replace('_',' ', $role) }}"
            value="{{ \App\Models\User::role($role)->count() }}" />
    </div>
    @endforeach
</div>

{{-- Users Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold" style="color:var(--dtc-text);">All Users</span>
        <span style="font-size:13px; color:var(--dtc-text-secondary);">{{ $users->total() }} total</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-bordered mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($users as $user)
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:38px; height:38px; border-radius:50%;
                                        background:linear-gradient(135deg,var(--dtc-primary),#1a5feb);
                                        display:flex; align-items:center; justify-content:center;
                                        color:#fff; font-weight:700; font-size:14px; flex-shrink:0;">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>

                            <div style="font-size:13px; font-weight:600; color:var(--dtc-text);">
                                {{ $user->name }}
                            </div>
                        </div>
                    </td>

                    <td style="font-size:13px; color:var(--dtc-text-secondary);">
                        {{ $user->email }}
                    </td>

                    <td>
                        @foreach($user->roles as $role)
                            @php
                                $rc = [
                                    'admin'         => ['bg'=>'var(--dtc-primary-soft)','color'=>'var(--dtc-primary)'],
                                    'registrar'     => ['bg'=>'#DCFCE7','color'=>'#15803D'],
                                    'cashier'       => ['bg'=>'#FEF9C3','color'=>'#A16207'],
                                    'student'       => ['bg'=>'#EDE9FE','color'=>'#7C3AED'],
                                    'alumni'        => ['bg'=>'#FEE2E2','color'=>'#DC2626'],
                                    'new_applicant' => ['bg'=>'#DBEAFE','color'=>'#1D4ED8'],
                                ][$role->name] ?? ['bg'=>'var(--dtc-border-soft)','color'=>'var(--dtc-text-secondary)'];
                            @endphp

                            <span style="background:{{ $rc['bg'] }};
                                         color:{{ $rc['color'] }};
                                         font-size:11px;
                                         font-weight:700;
                                         padding:3px 10px;
                                         border-radius:20px;
                                         text-transform:capitalize;">
                                {{ str_replace('_',' ', $role->name) }}
                            </span>
                        @endforeach
                    </td>

                    <td>
                        @php
                            $statusVariant = ['active' => 'success', 'locked' => 'danger', 'pending' => 'warning'][$user->status] ?? 'neutral';
                        @endphp
                        <x-dtc.status-badge :status="$user->status" :variant="$statusVariant" />
                    </td>

                    <td style="font-size:12px; color:var(--dtc-text-muted);">
                        {{ $user->created_at->format('M d, Y') }}
                    </td>

                    <td>
                        <div style="display:flex; gap:6px;">
                            <button type="button"
                                    class="btn-edit-user"
                                    data-id="{{ $user->id }}"
                                    data-url="{{ route('admin.users.edit', $user) }}"
                                    style="background:var(--dtc-primary-soft);
                                           color:var(--dtc-primary);
                                           border:none;
                                           padding:5px 12px;
                                           border-radius:8px;
                                           font-size:11px;
                                           font-weight:600;
                                           cursor:pointer;">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>

                            @if($user->id !== auth()->id())
                            <form method="POST"
                                  action="{{ route('admin.users.destroy', $user) }}">
                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        style="background:#FEE2E2;
                                               color:#DC2626;
                                               border:none;
                                               padding:5px 12px;
                                               border-radius:8px;
                                               font-size:11px;
                                               font-weight:600;
                                               cursor:pointer;"
                                        onclick="return confirm('Delete {{ $user->name }}?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>

                @empty
                <tr>
                    <td colspan="6"
                        class="text-center py-5"
                        style="color:var(--dtc-text-muted);">
                        No users found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

    {{-- Pagination --}}
        <div class="card-footer"
            style="background:var(--dtc-surface); border-top:1px solid var(--dtc-border);">

            <div class="d-flex justify-content-between align-items-center flex-wrap"
                style="gap:15px;">

                <small style="color:var(--dtc-text-secondary);">
                    Showing
                    <strong>{{ $users->firstItem() }}</strong>
                    to
                    <strong>{{ $users->lastItem() }}</strong>
                    of
                    <strong>{{ $users->total() }}</strong>
                    users
                </small>

                <div class="user-pagination">
                    {{ $users->onEachSide(1)->links('pagination::bootstrap-4') }}
                </div>

            </div>
        </div>
</div>

<!-- CREATE USER MODAL -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border:none; border-radius:20px; overflow:hidden;">

            {{-- Header --}}
            <div style="background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                        padding:24px 28px; display:flex; justify-content:space-between;
                        align-items:center;">
                <div>
                    <h5 style="color:#fff; font-weight:700; margin:0 0 4px;">
                        <i class="fas fa-user-plus mr-2" style="color:#FFC72C;"></i>
                        Add New User
                    </h5>
                    <p style="color:rgba(255,255,255,0.7); font-size:12px; margin:0;">
                        Create an account and assign a role
                    </p>
                </div>
                <button type="button" class="close" data-dismiss="modal"
                        style="color:#fff; opacity:1; font-size:22px;">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="modal-body" style="padding:28px;">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-size:13px; font-weight:600; color:#374151;">
                                    Full Name
                                </label>
                                <div style="position:relative; height:40px;">
                                    <i class="fas fa-user" style="position:absolute; left:14px;
                                       top:50%; transform:translateY(-50%); color:var(--dtc-text-muted); font-size:13px; line-height:1; z-index:2; pointer-events:none;"></i>
                                    <input type="text" name="name" class="form-control"
                                           style="padding-left:38px; height:40px;"
                                           value="{{ old('name') }}"
                                           placeholder="Enter full name" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-size:13px; font-weight:600; color:#374151;">
                                    Email Address
                                </label>
                                <div style="position:relative;">
                                    <i class="fas fa-envelope" style="position:absolute; left:14px;
                                       top:50%; transform:translateY(-50%); color:var(--dtc-text-muted); font-size:13px; line-height:1; pointer-events:none;"></i>
                                    <input type="email" name="email" class="form-control"
                                           style="padding-left:38px;"
                                           value="{{ old('email') }}"
                                           placeholder="Enter email address" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size:13px; font-weight:600; color:#374151;">
                            Temporary Password
                        </label>
                        <div style="position:relative;">
                            <i class="fas fa-lock" style="position:absolute; left:14px;
                               top:50%; transform:translateY(-50%); color:var(--dtc-text-muted); font-size:13px; line-height:1; pointer-events:none;"></i>
                            <input type="text" name="password" class="form-control"
                                   style="padding-left:38px;"
                                   placeholder="Min. 6 characters" required>
                        </div>
                        <small style="color:var(--dtc-text-muted); font-size:11px; margin-top:4px; display:block;">
                            The user should change this after first login.
                        </small>
                    </div>

                    <div class="form-group mb-0">
                        <label style="font-size:13px; font-weight:600; color:#374151; margin-bottom:10px; display:block;">
                            Assign Role
                        </label>
                        <div class="row">
                            @foreach($roles as $role)
                            @php
                                $roleConfig = [
                                    'registrar'     => ['bg'=>'#DCFCE7','color'=>'#15803D','icon'=>'fa-id-card'],
                                    'cashier'       => ['bg'=>'#FEF9C3','color'=>'#A16207','icon'=>'fa-cash-register'],
                                    'student'       => ['bg'=>'#EDE9FE','color'=>'#7C3AED','icon'=>'fa-user-graduate'],
                                    'alumni'        => ['bg'=>'#FEE2E2','color'=>'#DC2626','icon'=>'fa-user-tie'],
                                    'new_applicant' => ['bg'=>'#DBEAFE','color'=>'#1D4ED8','icon'=>'fa-user-clock'],
                                ][$role->name] ?? ['bg'=>'#EEF2FF','color'=>'#0F4CDB','icon'=>'fa-user'];
                            @endphp
                            <div class="col-6 col-md-4 mb-2">
                                <label style="cursor:pointer; width:100%; margin:0;">
                                    <input type="radio" name="role" value="{{ $role->name }}"
                                           style="display:none;" class="create-role-radio">
                                    <div class="create-role-option"
                                         style="border:2px solid var(--dtc-border); border-radius:12px;
                                                padding:12px; display:flex; align-items:center;
                                                gap:10px; transition:all 0.2s; background:var(--dtc-surface-soft);">
                                        <div style="width:32px; height:32px; border-radius:8px;
                                                    background:{{ $roleConfig['bg'] }}; display:flex;
                                                    align-items:center; justify-content:center; flex-shrink:0;">
                                            <i class="fas {{ $roleConfig['icon'] }}"
                                               style="font-size:13px; color:{{ $roleConfig['color'] }};"></i>
                                        </div>
                                        <span style="font-size:12px; font-weight:600; color:var(--dtc-text);
                                                     text-transform:capitalize;">
                                            {{ str_replace('_',' ', $role->name) }}
                                        </span>
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                </div>

                <div class="modal-footer" style="padding:16px 28px; border-top:1px solid #F1F5F9;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus mr-1"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border:none; border-radius:20px; overflow:hidden;">

            {{-- Header --}}
            <div style="background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                        padding:24px 28px; display:flex; justify-content:space-between;
                        align-items:center;">
                <div style="display:flex; align-items:center; gap:14px;">
                    <div id="edit-avatar"
                         style="width:44px; height:44px; border-radius:50%;
                                background:rgba(255,255,255,0.2); display:flex;
                                align-items:center; justify-content:center;
                                color:#fff; font-weight:800; font-size:18px;
                                border:2px solid rgba(255,255,255,0.3); flex-shrink:0;">
                        U
                    </div>
                    <div>
                        <h5 style="color:#fff; font-weight:700; margin:0 0 2px;" id="edit-modal-name">
                            Edit User
                        </h5>
                        <p style="color:rgba(255,255,255,0.7); font-size:12px; margin:0;" id="edit-modal-email">
                            user@dtc.edu.ph
                        </p>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal"
                        style="color:#fff; opacity:1; font-size:22px;">&times;</button>
            </div>

            <form method="POST" id="edit-user-form" action="">
                @csrf @method('PUT')
                <div class="modal-body" style="padding:28px;">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-size:13px; font-weight:600; color:#374151;">
                                    Full Name
                                </label>
                                <div style="position:relative;">
                                    <i class="fas fa-user" style="position:absolute; left:14px;
                                       top:50%; transform:translateY(-50%); color:var(--dtc-text-muted); font-size:13px;"></i>
                                    <input type="text" name="name" id="edit-name"
                                           class="form-control" style="padding-left:38px;" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-size:13px; font-weight:600; color:#374151;">
                                    Email Address
                                </label>
                                <div style="position:relative;">
                                    <i class="fas fa-envelope" style="position:absolute; left:14px;
                                       top:50%; transform:translateY(-50%); color:var(--dtc-text-muted); font-size:13px;"></i>
                                    <input type="email" name="email" id="edit-email"
                                           class="form-control" style="padding-left:38px;" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-size:13px; font-weight:600; color:#374151;">
                                    Role
                                </label>
                                <select name="role" id="edit-role" class="form-control" required>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->name }}">
                                            {{ ucfirst(str_replace('_',' ', $role->name)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label style="font-size:13px; font-weight:600; color:#374151; margin-bottom:10px; display:block;">
                                    Account Status
                                </label>
                                <div style="display:flex; gap:8px;">
                                    @foreach(['active','pending','locked'] as $status)
                                    <label style="cursor:pointer; flex:1; margin:0;">
                                        <input type="radio" name="status" value="{{ $status }}"
                                               class="edit-status-radio" style="display:none;">
                                        <div class="edit-status-option"
                                             style="border:2px solid var(--dtc-border); border-radius:10px;
                                                    padding:10px 8px; text-align:center;
                                                    transition:all 0.2s; background:var(--dtc-surface-soft); cursor:pointer;">
                                            <i class="fas {{ $status === 'active' ? 'fa-check-circle' : ($status === 'locked' ? 'fa-lock' : 'fa-clock') }}"
                                               style="font-size:16px; display:block; margin-bottom:4px;
                                                      color:{{ $status === 'active' ? '#22C55E' : ($status === 'locked' ? '#EF4444' : '#F59E0B') }};"></i>
                                            <div style="font-size:11px; font-weight:600; color:var(--dtc-text);">
                                                {{ ucfirst($status) }}
                                            </div>
                                        </div>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer" style="padding:16px 28px; border-top:1px solid #F1F5F9;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
@section('css')
<style>
/* =========================================================
   MANAGE USERS — DARK MODE
   ========================================================= */

body.dtc-dark {
    --users-bg: #0f172a;
    --users-surface: #111827;
    --users-surface-2: #172033;
    --users-border: #334155;
    --users-border-soft: #263449;
    --users-text: #f1f5f9;
    --users-text-muted: var(--dtc-text-muted);
}


/* =========================================================
   PAGE BACKGROUND
   ========================================================= */

body.dtc-dark .content-wrapper {
    background: var(--users-bg) !important;
}

body.dtc-dark .content-header h4 {
    color: var(--users-text) !important;
}

body.dtc-dark .content-header p {
    color: var(--users-text-muted) !important;
}


/* =========================================================
   ALL CARDS
   ========================================================= */

body.dtc-dark .card {
    background: var(--users-surface) !important;
    color: var(--users-text) !important;

    border: 1px solid var(--users-border) !important;
    box-shadow: none !important;
}


/* =========================================================
   STAT CARDS
   ========================================================= */

body.dtc-dark .row .card {
    background: var(--users-surface) !important;
    border: 1px solid var(--users-border) !important;
}

body.dtc-dark .row .card div {
    color: var(--users-text) !important;
}

body.dtc-dark .row .card div:last-child {
    color: var(--users-text-muted) !important;
}


/* =========================================================
   USERS TABLE CARD
   ========================================================= */

body.dtc-dark .card-header {
    background: var(--users-surface) !important;
    border-bottom: 1px solid var(--users-border) !important;
    color: var(--users-text) !important;
}

body.dtc-dark .card-header span {
    color: var(--users-text) !important;
}

body.dtc-dark .card-header span:last-child {
    color: var(--users-text-muted) !important;
}


/* =========================================================
   TABLE
   ========================================================= */

body.dtc-dark .table {
    color: var(--users-text) !important;
    background: var(--users-surface) !important;
}

body.dtc-dark .table thead {
    background: var(--users-surface-2) !important;
}

body.dtc-dark .table thead th {
    color: var(--users-text-muted) !important;
    background: var(--users-surface-2) !important;
    border-color: var(--users-border) !important;
}

body.dtc-dark .table tbody {
    background: var(--users-surface) !important;
}

body.dtc-dark .table tbody tr {
    background: var(--users-surface) !important;
}

body.dtc-dark .table tbody tr:hover {
    background: #1a2638 !important;
}

body.dtc-dark .table td {
    color: var(--users-text) !important;
    border-color: var(--users-border-soft) !important;
}


/* User name */
body.dtc-dark .table td div[style*="font-weight:600"] {
    color: var(--users-text) !important;
}


/* Email */
body.dtc-dark .table td[style*="color:var(--dtc-text-secondary)"] {
    color: var(--users-text-muted) !important;
}


/* Joined date */
body.dtc-dark .table td[style*="color:var(--dtc-text-muted)"] {
    color: var(--users-text-muted) !important;
}


/* =========================================================
   CARD FOOTER / PAGINATION
   ========================================================= */

body.dtc-dark .card-footer {
    background: var(--users-surface) !important;
    border-top: 1px solid var(--users-border) !important;
    color: var(--users-text-muted) !important;
}

body.dtc-dark .card-footer small {
    color: var(--users-text-muted) !important;
}

body.dtc-dark .card-footer strong {
    color: var(--users-text) !important;
}


/* Bootstrap pagination */
body.dtc-dark .user-pagination .page-link {
    background: var(--users-surface-2) !important;
    color: var(--users-text-muted) !important;
    border-color: var(--users-border) !important;
}

body.dtc-dark .user-pagination .page-item.active .page-link {
    background: #0F4CDB !important;
    color: #fff !important;
    border-color: #0F4CDB !important;
}

body.dtc-dark .user-pagination .page-item.disabled .page-link {
    background: #0b1220 !important;
    color: #475569 !important;
    border-color: var(--users-border) !important;
}


/* =========================================================
   EDIT / DELETE BUTTONS
   ========================================================= */

body.dtc-dark .btn-edit-user {
    background: #172554 !important;
    color: #60a5fa !important;
    border: 1px solid #1e40af !important;
}

body.dtc-dark .btn-edit-user:hover {
    background: #1e3a8a !important;
    color: #bfdbfe !important;
}

body.dtc-dark td form button {
    background: #3f1d25 !important;
    color: #f87171 !important;
    border: 1px solid #7f1d1d !important;
}

body.dtc-dark td form button:hover {
    background: #581c24 !important;
}


/* =========================================================
   CREATE / EDIT MODALS
   ========================================================= */

body.dtc-dark .modal-content {
    background: var(--users-surface) !important;
    color: var(--users-text) !important;
    border: 1px solid var(--users-border) !important;
}

body.dtc-dark .modal-body {
    background: var(--users-surface) !important;
}

body.dtc-dark .modal-footer {
    background: var(--users-surface) !important;
    border-top: 1px solid var(--users-border) !important;
}


/* Modal labels */
body.dtc-dark .modal label {
    color: var(--users-text) !important;
}


/* Modal inputs */
body.dtc-dark .modal .form-control,
body.dtc-dark .modal select,
body.dtc-dark .modal input {
    background: #0f172a !important;
    color: var(--users-text) !important;
    border: 1px solid var(--users-border) !important;
}

body.dtc-dark .modal .form-control::placeholder {
    color: var(--dtc-text-secondary) !important;
}

body.dtc-dark .modal .form-control:focus,
body.dtc-dark .modal select:focus {
    background: #111827 !important;
    color: #fff !important;
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 2px rgba(59,130,246,.15) !important;
}


/* =========================================================
   CREATE ROLE OPTIONS
   ========================================================= */

body.dtc-dark .create-role-option {
    background: #111827 !important;
    border-color: var(--users-border) !important;
}

body.dtc-dark .create-role-option span {
    color: var(--users-text) !important;
}

body.dtc-dark .create-role-option:hover {
    background: #172033 !important;
    border-color: #475569 !important;
}


/* =========================================================
   EDIT STATUS OPTIONS
   ========================================================= */

body.dtc-dark .edit-status-option {
    background: #111827 !important;
    border-color: var(--users-border) !important;
}

body.dtc-dark .edit-status-option div {
    color: var(--users-text) !important;
}

body.dtc-dark .edit-status-option:hover {
    background: #172033 !important;
    border-color: #475569 !important;
}


/* =========================================================
   ALERTS
   ========================================================= */

body.dtc-dark .alert-success {
    background: #052e1b !important;
    color: #86efac !important;
    border: 1px solid #166534 !important;
}

body.dtc-dark .alert-danger {
    background: #3f0d16 !important;
    color: #fca5a5 !important;
    border: 1px solid #991b1b !important;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 767.98px) {

    body.dtc-dark .table-responsive {
        border: 1px solid var(--users-border);
        border-radius: 10px;
    }

    body.dtc-dark .card {
        border-radius: 14px !important;
    }

    body.dtc-dark .card-header {
        padding: 14px !important;
    }

}
</style>
@endsection

@section('js')
<script>
// ── Create User — role radio styling ────────────────────────────────────
document.querySelectorAll('.create-role-radio').forEach(radio => {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.create-role-option').forEach(opt => {
            opt.style.borderColor = 'var(--dtc-border)';
            opt.style.background  = 'var(--dtc-surface-soft)';
        });
        this.nextElementSibling.style.borderColor = '#0F4CDB';
        this.nextElementSibling.style.background  = '#EEF2FF';
    });
});

// ── Edit User — load data into modal via AJAX ────────────────────────────
document.querySelectorAll('.btn-edit-user').forEach(btn => {
    btn.addEventListener('click', function () {
        const url = this.dataset.url;
        const id  = this.dataset.id;

        // Set form action
        document.getElementById('edit-user-form').action =
            '{{ url("admin/users") }}/' + id;

        // Show loading state
        document.getElementById('edit-modal-name').textContent  = 'Loading...';
        document.getElementById('edit-modal-email').textContent = '';

        // Open modal
        $('#editUserModal').modal('show');

        // Fetch user data
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(user => {
            // Fill header
            document.getElementById('edit-avatar').textContent     = user.name.charAt(0).toUpperCase();
            document.getElementById('edit-modal-name').textContent  = user.name;
            document.getElementById('edit-modal-email').textContent = user.email;

            // Fill fields
            document.getElementById('edit-name').value  = user.name;
            document.getElementById('edit-email').value = user.email;
            document.getElementById('edit-role').value  = user.role;

            // Set status radio
            document.querySelectorAll('.edit-status-radio').forEach(r => {
                r.checked = (r.value === user.status);
                const opt = r.nextElementSibling;
                if (r.value === user.status) {
                    opt.style.borderColor = '#0F4CDB';
                    opt.style.background  = '#EEF2FF';
                } else {
                    opt.style.borderColor = 'var(--dtc-border)';
                    opt.style.background  = 'var(--dtc-surface-soft)';
                }
            });
        })
        .catch(() => {
            document.getElementById('edit-modal-name').textContent = 'Error loading user';
        });
    });
});

// ── Edit status radio styling ────────────────────────────────────────────
document.querySelectorAll('.edit-status-radio').forEach(radio => {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.edit-status-option').forEach(opt => {
            opt.style.borderColor = 'var(--dtc-border)';
            opt.style.background  = 'var(--dtc-surface-soft)';
        });
        this.nextElementSibling.style.borderColor = '#0F4CDB';
        this.nextElementSibling.style.background  = '#EEF2FF';
    });
});

// ── Auto-open create modal if validation errors ──────────────────────────
@if($errors->any() && old('name'))
    $(document).ready(function() {
        $('#createUserModal').modal('show');
    });
@endif
</script>
@endsection