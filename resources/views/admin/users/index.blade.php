@extends('adminlte::page')
@include('partials.navbar')

@section('plugins.adminUsers', true)

@section('title', 'Manage Users')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Manage Users</h4>
            <p class="mb-0 u-text-secondary-sm" >
                Create and manage system user accounts
            </p>
        </div>
        <button type="button" class="btn btn-primary btn-sm add-user-btn" data-toggle="modal" data-target="#createUserModal">
            <i data-lucide="user-plus" class="mr-1"></i> 
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
            'admin'         => ['icon' => 'user-check',    'color' => 'primary'],
            'registrar'     => ['icon' => 'id-card',        'color' => 'success'],
            'cashier'       => ['icon' => 'receipt',  'color' => 'warning'],
            'student'       => ['icon' => 'graduation-cap',  'color' => 'info'],
            'alumni'        => ['icon' => 'award',          'color' => 'danger'],
            'new_applicant' => ['icon' => 'user-plus',      'color' => 'neutral'],
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
        <span class="font-weight-bold u-text" >All Users</span>
        <span  class="u-text-secondary-sm">{{ $users->total() }} total</span>
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
                        <div  class="u-flex-center-gap-12">
                            <div style="width:38px; height:38px; border-radius:50%;
                                        background:linear-gradient(135deg,var(--dtc-primary),#1a5feb);
                                        display:flex; align-items:center; justify-content:center;
                                        color:#fff; font-weight:700; font-size:14px; flex-shrink:0;">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>

                            <div  class="u-text-sm-bold-primary">
                                {{ $user->name }}
                            </div>
                        </div>
                    </td>

                    <td  class="u-text-secondary-sm">
                        {{ $user->email }}
                    </td>

                    <td>
                        @foreach($user->roles as $role)
                            @php
                                $rc = [
                                    'admin'         => ['tone'=>'primary'],
                                    'registrar'     => ['tone'=>'success'],
                                    'cashier'       => ['tone'=>'warning'],
                                    'student'       => ['tone'=>'purple'],
                                    'alumni'        => ['tone'=>'danger'],
                                    'new_applicant' => ['tone'=>'info'],
                                ][$role->name] ?? ['tone'=>'neutral'];
                            @endphp

                            <span class="dtc-status-badge is-{{ $rc['tone'] ?? 'neutral' }}"
                                  style="font-weight:700; text-transform:capitalize;">
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
                        <div  class="u-actions-gap">
                            <button type="button"
                                    class="btn-edit-user"
                                    data-id="{{ $user->id }}"
                                    data-url="{{ route('admin.users.edit', $user) }}"
                                    style="background:var(--dtc-primary-soft);
                                           color:var(--dtc-primary);
                                           border:none;
                                           width:34px;
                                           height:34px;
                                           display:flex;
                                           align-items:center;
                                           justify-content:center;
                                           padding:0;
                                           border-radius:8px;
                                           font-size:11px;
                                           font-weight:600;
                                           cursor:pointer;">
                                <i data-lucide="pencil"></i>
                            </button>

                            @if($user->id !== auth()->id())
                            <form method="POST"
                                  action="{{ route('admin.users.destroy', $user) }}">
                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        class="dtc-status-badge is-danger"
                                        style="font-size:11px; font-weight:700;
                                               border:none;
                                               width:34px;
                                               height:34px;
                                               display:flex;
                                               align-items:center;
                                               justify-content:center;
                                               padding:0;
                                               border-radius:8px;
                                               font-size:11px;
                                               font-weight:600;
                                               cursor:pointer;"
                                        onclick="return confirm('Delete {{ $user->name }}?')">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>

                @empty
                <tr>
                    <td colspan="6"
                        class="text-center py-5 u-text-muted"
                        >
                        No users found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

    {{-- Pagination --}}
        <div class="card-footer u-panel-footer"
            >

            <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-15"
                >

                <small  class="u-text-secondary">
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
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content dtc-modal-content">

            {{-- Header --}}
            <div class="dtc-modal-header">
                <div class="dtc-modal-header-icon">
                    <i data-lucide="user-plus"></i>
                </div>
                <div class="dtc-modal-header-text">
                    <h5>Add New User</h5>
                    <p>Create an account and assign a role</p>
                </div>
                <button type="button" class="dtc-modal-close" data-dismiss="modal" aria-label="Close">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="modal-body dtc-modal-body">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="dtc-form-label">Full Name</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="user" class="dtc-input-icon"></i>
                                    <input type="text" name="name" class="form-control dtc-input-with-icon"
                                           value="{{ old('name') }}"
                                           placeholder="Enter full name" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="dtc-form-label">Email Address</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="mail" class="dtc-input-icon"></i>
                                    <input type="email" name="email" class="form-control dtc-input-with-icon"
                                           value="{{ old('email') }}"
                                           placeholder="Enter email address" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="dtc-form-label">Temporary Password</label>
                        <div class="dtc-input-icon-group">
                            <i data-lucide="lock" class="dtc-input-icon"></i>
                            <input type="text" name="password" class="form-control dtc-input-with-icon"
                                   placeholder="Min. 6 characters" required>
                        </div>
                        <small class="dtc-form-hint">
                            The user should change this after first login.
                        </small>
                    </div>

                    <div class="form-group mb-0">
                        <label class="dtc-form-label dtc-form-label-block">Assign Role</label>
                        <div class="row">
                            @foreach($roles as $role)
                            @php
                                $roleConfig = [
                                    'registrar'     => ['tone'=>'success', 'icon'=>'id-card'],
                                    'cashier'       => ['tone'=>'warning', 'icon'=>'receipt'],
                                    'student'       => ['tone'=>'purple',  'icon'=>'graduation-cap'],
                                    'alumni'        => ['tone'=>'danger',  'icon'=>'award'],
                                    'new_applicant' => ['tone'=>'info',    'icon'=>'user-plus'],
                                ][$role->name] ?? ['tone'=>'primary', 'icon'=>'user'];
                            @endphp
                            <div class="col-6 col-md-4 mb-2">
                                <label class="dtc-role-option-label">
                                    <input type="radio" name="role" value="{{ $role->name }}"
                                           class="create-role-radio dtc-sr-only">
                                    <div class="create-role-option">
                                        <div class="dtc-icon-swatch is-{{ $roleConfig['tone'] }} dtc-role-icon" data-role="{{ $role->name }}">
                                            <i data-lucide="{{ $roleConfig['icon'] }}"></i>
                                        </div>
                                        <span class="dtc-role-name">
                                            {{ str_replace('_',' ', $role->name) }}
                                        </span>
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                </div>

                <div class="modal-footer dtc-modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="user-plus" class="mr-1"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content dtc-modal-content">

            {{-- Header --}}
            <div class="dtc-modal-header">
                <div class="dtc-modal-header-avatar" id="edit-avatar">U</div>
                <div class="dtc-modal-header-text">
                    <h5 id="edit-modal-name">Edit User</h5>
                    <p id="edit-modal-email">user@dtc.edu.ph</p>
                </div>
                <button type="button" class="dtc-modal-close" data-dismiss="modal" aria-label="Close">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <form method="POST" id="edit-user-form" action="">
                @csrf @method('PUT')
                <div class="modal-body dtc-modal-body">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="dtc-form-label">Full Name</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="user" class="dtc-input-icon"></i>
                                    <input type="text" name="name" id="edit-name"
                                           class="form-control dtc-input-with-icon" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="dtc-form-label">Email Address</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="mail" class="dtc-input-icon"></i>
                                    <input type="email" name="email" id="edit-email"
                                           class="form-control dtc-input-with-icon" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="dtc-form-label">Role</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="user-check" class="dtc-input-icon"></i>
                                    <select name="role" id="edit-role"
                                            class="form-control dtc-input-with-icon" required>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}">
                                                {{ ucfirst(str_replace('_',' ', $role->name)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="dtc-form-label dtc-form-label-block">Account Status</label>
                                <div style="display:flex; gap:8px;">
                                    @foreach(['active','pending','locked'] as $status)
                                    <label class="dtc-status-option-label">
                                        <input type="radio" name="status" value="{{ $status }}"
                                               class="edit-status-radio dtc-sr-only">
                                        <div class="edit-status-option">
                                            <i data-lucide="{{ $status === 'active' ? 'check-circle' : ($status === 'locked' ? 'lock' : 'clock') }}" style="color:{{ $status === 'active' ? 'var(--dtc-success)' : ($status === 'locked' ? 'var(--dtc-danger)' : 'var(--dtc-warning)') }};"></i>
                                            <div class="dtc-status-label">{{ ucfirst($status) }}</div>
                                        </div>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer dtc-modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save" class="mr-1"></i> Save Changes
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
   CREATE / EDIT USER MODALS — BASE STRUCTURE
   =========================================================
   NOTE: dtc-theme.css defines a global rule:
       .form-control { padding: 10px 14px !important; ... }
   Because it uses !important, any inline padding-left set on
   an icon-prefixed input was being silently overridden, which
   pushed input text/placeholders underneath the icon. The
   `.dtc-input-icon-group` + `.dtc-input-with-icon` pair below
   uses a more specific selector so the icon offset always wins.
   ========================================================= */

.dtc-modal-content {
    border: none;
    border-radius: 20px;
    overflow: hidden;
}

.dtc-modal-header {
    background: linear-gradient(135deg, #0F4CDB, #1a5feb);
    padding: 22px 28px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.dtc-modal-header-icon,
.dtc-modal-header-avatar {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 18px;
    line-height: 1;
}

.dtc-modal-header-icon {
    border-radius: 12px;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    color: #FFC72C;
}

.dtc-modal-header-avatar {
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    border: 2px solid rgba(255,255,255,0.3);
    color: #fff;
    font-weight: 800;
}

.dtc-modal-header-text {
    flex: 1 1 auto;
    min-width: 0;
}

.dtc-modal-header-text h5 {
    color: #fff;
    font-weight: 700;
    margin: 0 0 2px;
    font-size: 16.5px;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dtc-modal-header-text p {
    color: rgba(255,255,255,0.72);
    font-size: 12px;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dtc-modal-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: rgba(255,255,255,0.15);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
    cursor: pointer;
    transition: background 0.15s ease;
    padding: 0;
}

.dtc-modal-close:hover,
.dtc-modal-close:focus {
    background: rgba(255,255,255,0.28);
    color: #fff;
}

.dtc-modal-body {
    padding: 26px 28px;
}

.dtc-modal-footer {
    padding: 16px 28px;
    border-top: 1px solid var(--dtc-border-soft);
    gap: 10px;
}

.dtc-form-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--dtc-text);
    margin-bottom: 6px;
}

.dtc-form-label-block {
    display: block;
    margin-bottom: 10px;
}

.dtc-form-hint {
    color: var(--dtc-text-muted);
    font-size: 11px;
    margin-top: 5px;
    display: block;
}

/* ---- Icon-prefixed inputs ---- */
.dtc-input-icon-group {
    position: relative;
    display: flex;
    align-items: center;
}

.dtc-input-icon-group .dtc-input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    text-align: center;
    color: var(--dtc-text-muted);
    font-size: 13px;
    line-height: 1;
    pointer-events: none;
    z-index: 2;
}

.dtc-input-icon-group .form-control.dtc-input-with-icon {
    padding-left: 42px !important;
}

/* ---- Role selection cards (create modal) ---- */
.dtc-role-option-label {
    cursor: pointer;
    width: 100%;
    margin: 0;
    display: block;
}

.create-role-option {
    border: 2px solid var(--dtc-border);
    border-radius: 12px;
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
    background: var(--dtc-surface-soft);
}

.dtc-role-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 13px;
    line-height: 1;
}

.dtc-role-name {
    font-size: 12px;
    font-weight: 600;
    color: var(--dtc-text);
    text-transform: capitalize;
    line-height: 1.3;
}

/* ---- Status selection cards (edit modal) ---- */
.dtc-status-option-label {
    cursor: pointer;
    flex: 1;
    margin: 0;
}

.edit-status-option {
    border: 2px solid var(--dtc-border);
    border-radius: 10px;
    padding: 10px 8px;
    text-align: center;
    transition: all 0.2s ease;
    background: var(--dtc-surface-soft);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.edit-status-option i {
    font-size: 16px;
    line-height: 1;
}

.dtc-status-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--dtc-text);
    line-height: 1.2;
}

.dtc-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0,0,0,0);
    white-space: nowrap;
    border: 0;
}


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
   ROLE BADGES / ROLE ICONS
   ========================================================= */

body.dtc-dark [data-role="registrar"] {
    background: rgba(21,128,61,0.18) !important;
    color: #4ade80 !important;
}

body.dtc-dark [data-role="cashier"] {
    background: rgba(161,98,7,0.18) !important;
    color: #facc15 !important;
}

body.dtc-dark [data-role="student"] {
    background: rgba(124,58,237,0.18) !important;
    color: #c4b5fd !important;
}

body.dtc-dark [data-role="alumni"] {
    background: rgba(220,38,38,0.18) !important;
    color: #fca5a5 !important;
}

body.dtc-dark [data-role="new_applicant"] {
    background: rgba(29,78,216,0.18) !important;
    color: #93c5fd !important;
}

body.dtc-dark [data-role] i {
    color: inherit !important;
}


/* =========================================================
   CREATE / EDIT — SELECTED OPTION HIGHLIGHT
   (the base .create-role-option / .edit-status-option rules
   above are a catch-all, so the checked option needs a more
   specific selector to stay visible in dark mode)
   ========================================================= */

body.dtc-dark .create-role-radio:checked + .create-role-option,
body.dtc-dark .edit-status-radio:checked + .edit-status-option {
    background: rgba(15,76,219,0.16) !important;
    border-color: var(--dtc-primary) !important;
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
        this.nextElementSibling.style.borderColor = 'var(--dtc-primary)';
        this.nextElementSibling.style.background  = 'var(--dtc-primary-soft)';
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
                    opt.style.borderColor = 'var(--dtc-primary)';
                    opt.style.background  = 'var(--dtc-primary-soft)';
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
        this.nextElementSibling.style.borderColor = 'var(--dtc-primary)';
        this.nextElementSibling.style.background  = 'var(--dtc-primary-soft)';
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