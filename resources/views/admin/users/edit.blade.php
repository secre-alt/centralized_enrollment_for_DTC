@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Edit User')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Edit User</h4>
            <p class="mb-0 u-text-secondary-sm" >
                Update account details and role
            </p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="mr-1"></i> Back to Users
        </a>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">

        {{-- User Profile Card --}}
        <div class="card mb-3"
             style="background:linear-gradient(135deg,#0F4CDB,#1a5feb); border:none;">
            <div class="card-body p-4">
                <div style="display:flex; align-items:center; gap:16px;">
                    <div style="width:56px; height:56px; border-radius:50%;
                                background:rgba(255,255,255,0.2); display:flex;
                                align-items:center; justify-content:center;
                                color:#fff; font-weight:800; font-size:22px; flex-shrink:0;
                                border:2px solid rgba(255,255,255,0.3);">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div>
                        <div style="font-size:16px; font-weight:700; color:#fff;">
                            {{ $user->name }}
                        </div>
                        <div style="font-size:13px; color:rgba(255,255,255,0.75);">
                            {{ $user->email }}
                        </div>
                        <div style="font-size:12px; color:#FFC72C; margin-top:4px;
                                    font-weight:600;">
                            Joined {{ $user->created_at->format('M d, Y') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header font-weight-bold u-text" >
                <i data-lucide="pencil" class="mr-2 u-link"></i>
                Edit Account
            </div>
            <div class="card-body">

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf @method('PUT')

                    <div class="form-group">
                        <label>Full Name</label>
                        <div  class="u-relative">
                            <i data-lucide="user" style="position:absolute; left:14px;
                               top:13px; color:var(--dtc-text-muted); font-size:13px; pointer-events:none;"></i>
                            <input type="text" name="name" class="form-control u-pl-38"
                                   
                                   value="{{ old('name', $user->name) }}" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <div  class="u-relative">
                            <i data-lucide="mail" style="position:absolute; left:14px;
                               top:13px; color:var(--dtc-text-muted); font-size:13px; pointer-events:none;"></i>
                            <input type="email" name="email" class="form-control u-pl-38"
                                   
                                   value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" class="form-control" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}"
                                    {{ $user->hasRole($role->name) ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_',' ', $role->name)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Account Status</label>
                        <div class="row">
                            @foreach(['active','pending','locked'] as $status)
                            <div class="col-4">
                                <label style="cursor:pointer; width:100%;">
                                    <input type="radio" name="status" value="{{ $status }}"
                                            class="status-radio u-hidden"
                                           {{ old('status', $user->status) === $status ? 'checked' : '' }}>
                                    <div class="status-option"
                                         style="border:2px solid var(--dtc-border); border-radius:12px;
                                                padding:12px; text-align:center; transition:all 0.2s;
                                                background:var(--dtc-surface-soft);">
                                        <i data-lucide="{{ $status === 'active' ? 'check-circle' : ($status === 'locked' ? 'lock' : 'clock') }}" style="font-size:18px; display:block; margin-bottom:6px;
                                                  color:{{ $status === 'active' ? 'var(--dtc-success)' : ($status === 'locked' ? 'var(--dtc-danger)' : 'var(--dtc-warning)') }};"></i>
                                        <div style="font-size:12px; font-weight:600; color:var(--dtc-text);">
                                            {{ ucfirst($status) }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex u-gap-10" >
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i data-lucide="save" class="mr-1"></i> Save Changes
                        </button>
                        <a href="{{ route('admin.users.index') }}"
                           class="btn btn-secondary flex-fill">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
document.querySelectorAll('.status-radio').forEach(radio => {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.status-option').forEach(opt => {
            opt.style.borderColor = 'var(--dtc-border)';
            opt.style.background = 'var(--dtc-surface-soft)';
        });
        this.nextElementSibling.style.borderColor = 'var(--dtc-primary)';
        this.nextElementSibling.style.background = 'var(--dtc-primary-soft)';
    });
    if (this.checked) this.dispatchEvent(new Event('change'));
});
</script>
@endsection