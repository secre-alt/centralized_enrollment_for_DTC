@extends('adminlte::page')
@include('partials.navbar')

@section('title', __('ui.profile.title'))

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">{{ __('ui.profile.title') }}</h4>
            <p class="mb-0 u-text-secondary-sm">{{ __('ui.profile.description') }}</p>
        </div>
    </div>
@endsection

@section('content')

@php
    $profileRoutePrefix = $profileRoutePrefix ?? 'portal.profile';
@endphp

@if ($errors->any() && !$errors->has('current_password') && !$errors->has('password'))
<div class="alert dtc-alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
    <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
    <span>{{ __('ui.profile.fix_errors') }}</span>
</div>
@endif
@if (session('success'))
<div class="alert alert-success mb-4" role="status">{{ session('success') }}</div>
@endif

<div class="row">
<div class="col-12" style="max-width:820px;">

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- HERO CARD                                                              --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="card mb-4 ps-hero-card">
    <div class="card-body d-flex align-items-center gap-3 flex-wrap">

        {{-- Avatar --}}
        <div class="ps-avatar-wrap">
            <x-dtc.user-avatar :user="$user" class="ps-avatar-initials" />
            <button type="button"
                    class="ps-avatar-edit-btn"
                    onclick="document.getElementById('avatarFileInput').click()"
                    title="{{ __('ui.profile.change_photo') }}"
                    aria-label="{{ __('ui.profile.change_photo') }}">
                <i data-lucide="camera" style="width:14px;height:14px;"></i>
            </button>
        </div>

        {{-- Identity --}}
        <div>
            <div class="ps-hero-name">{{ $user->name }}</div>
            <div class="ps-hero-email">{{ $user->email }}</div>
            <span class="ps-role-badge">
                <i data-lucide="shield" style="width:11px;height:11px;"></i>
                {{ $user->getRoleNames()->map(fn ($role) => ucfirst(str_replace('_', ' ', $role)))->implode(' / ') ?: 'User' }}
            </span>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- PERSONAL INFORMATION                                                   --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="card mb-4">
    <div class="card-header ps-card-header">
        <div class="ps-card-icon ps-icon-blue">
            <i data-lucide="user" style="width:17px;height:17px;"></i>
        </div>
        <div>
            <div class="ps-card-title">{{ __('ui.profile.personal_information') }}</div>
            <div class="ps-card-subtitle">{{ __('ui.profile.personal_description') }}</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST"
              action="{{ route($profileRoutePrefix . '.update') }}"
              enctype="multipart/form-data"
              id="profileForm">
            @csrf

            {{-- Hidden file input --}}
            <input type="file"
                   id="avatarFileInput"
                   name="avatar"
                   accept="image/jpeg,image/png,image/webp"
                   style="display:none">

            <div class="row">
                <div class="col-12 col-sm-6 mb-3">
                    <label for="name" class="ps-label">
                        {{ __('ui.profile.full_name') }} <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           class="form-control ps-input @error('name') is-invalid @enderror"
                           value="{{ old('name', $user->name) }}"
                           placeholder="{{ __('ui.profile.enter_full_name') }}"
                           autocomplete="name"
                           required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-sm-6 mb-3">
                    <label for="email" class="ps-label">
                        {{ __('ui.profile.email') }} <span class="text-danger">*</span>
                    </label>
                    <input type="email"
                           id="email"
                           name="email"
                           class="form-control ps-input @error('email') is-invalid @enderror"
                           value="{{ old('email', $user->email) }}"
                           placeholder="you@example.com"
                           autocomplete="email"
                           required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Avatar preview strip --}}
            <div id="avatarPreviewRow" style="display:none;" class="align-items-center gap-3 mb-3">
                <img id="avatarPreview"
                     alt="Preview"
                     class="ps-avatar-preview">
                <div>
                    <div style="font-size:.82rem;font-weight:600;color:var(--dtc-text);">{{ __('ui.profile.new_photo') }}</div>
                    <div style="font-size:.75rem;color:var(--dtc-text-secondary);">{{ __('ui.profile.save_to_apply') }}</div>
                </div>
                <button type="button"
                        id="clearAvatarBtn"
                        class="btn btn-sm btn-outline-secondary ml-auto">
                    <i data-lucide="x" style="width:13px;height:13px;"></i> {{ __('ui.profile.clear') }}
                </button>
            </div>

            @error('avatar')
                <div class="text-danger small mb-3 d-flex align-items-center gap-1">
                    <i data-lucide="alert-circle" style="width:13px;height:13px;"></i>
                    {{ $message }}
                </div>
            @enderror

        </form>

        {{-- NOTE: this button row sits OUTSIDE #profileForm on purpose. Nesting a
             second <form> (Remove Photo) inside #profileForm is invalid HTML — the
             browser silently drops the inner <form> tag but keeps its hidden
             @method('DELETE') input as a child of the OUTER form, which made every
             profile-form submission (Save Changes, avatar change) go out as DELETE
             instead of POST and 405. The "form" attribute below re-associates the
             Save Changes button with #profileForm without nesting. --}}
        <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
            <button type="submit" form="profileForm" class="btn btn-primary">
                <i data-lucide="save" class="mr-1" style="width:15px;height:15px;"></i>
                {{ __('ui.profile.save_changes') }}
            </button>

            @if ($user->avatar)
                <form method="POST"
                      action="{{ route($profileRoutePrefix . '.avatar.remove') }}"
                      data-confirm-title="{{ __('ui.profile.remove_photo_title') }}"
                      data-confirm-message="{{ __('ui.profile.remove_photo_confirm') }}"
                      data-confirm-ok="{{ __('ui.profile.remove_photo') }}"
                      class="m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i data-lucide="trash-2" class="mr-1" style="width:13px;height:13px;"></i>
                        {{ __('ui.profile.remove_photo') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header ps-card-header">
        <div class="ps-card-icon ps-icon-blue">
            <i data-lucide="languages" style="width:17px;height:17px;"></i>
        </div>
        <div>
            <div class="ps-card-title">{{ __('language.preference_title') }}</div>
            <div class="ps-card-subtitle">{{ __('language.preference_description') }}</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route($profileRoutePrefix . '.language.update') }}">
            @csrf
            <div class="form-group">
                <label for="preferred_language">{{ __('language.field_label') }}</label>
                <select id="preferred_language" name="preferred_language" class="form-control @error('preferred_language') is-invalid @enderror">
                    <option value="" {{ old('preferred_language', $user->preferred_language ?? '') === '' ? 'selected' : '' }}>
                        {{ __('language.use_system_default') }}
                    </option>
                    <option value="en" {{ old('preferred_language', $user->preferred_language ?? '') === 'en' ? 'selected' : '' }}>
                        {{ __('language.english') }}
                    </option>
                    <option value="fil" {{ old('preferred_language', $user->preferred_language ?? '') === 'fil' ? 'selected' : '' }}>
                        {{ __('language.tagalog') }}
                    </option>
                </select>
                @error('preferred_language')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary">{{ __('language.save') }}</button>
        </form>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- ACADEMIC RECORD (read-only, shown only when studentProfile exists)    --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
@if ($studentProfile)
<div class="card mb-4">
    <div class="card-header ps-card-header">
        <div class="ps-card-icon ps-icon-yellow">
            <i data-lucide="graduation-cap" style="width:17px;height:17px;"></i>
        </div>
        <div>
            <div class="ps-card-title">{{ __('ui.profile.academic_record') }}</div>
            <div class="ps-card-subtitle">{{ __('ui.profile.registrar_managed') }}</div>
        </div>
    </div>
    <div class="card-body p-0">

        <div class="ps-info-row">
            <div class="ps-info-icon"><i data-lucide="hash" style="width:14px;height:14px;"></i></div>
            <div>
                <div class="ps-info-label">{{ __('ui.profile.student_id') }}</div>
                <div class="ps-info-value">{{ $studentProfile->student_number ?? '—' }}</div>
            </div>
        </div>

        @if ($studentProfile->program)
        <div class="ps-info-row">
            <div class="ps-info-icon"><i data-lucide="book-open" style="width:14px;height:14px;"></i></div>
            <div>
                <div class="ps-info-label">{{ __('ui.profile.program') }}</div>
                <div class="ps-info-value">{{ $studentProfile->program->name }}</div>
            </div>
        </div>
        @endif

        @if ($studentProfile->enrolled_ay)
        <div class="ps-info-row">
            <div class="ps-info-icon"><i data-lucide="calendar" style="width:14px;height:14px;"></i></div>
            <div>
                <div class="ps-info-label">{{ __('ui.profile.academic_year') }}</div>
                <div class="ps-info-value">{{ $studentProfile->enrolled_ay }}</div>
            </div>
        </div>
        @endif

        @if ($user->hasRole('alumni') && $studentProfile->graduation_year)
        <div class="ps-info-row">
            <div class="ps-info-icon"><i data-lucide="award" style="width:14px;height:14px;"></i></div>
            <div>
                <div class="ps-info-label">{{ __('ui.profile.graduation_year') }}</div>
                <div class="ps-info-value">{{ $studentProfile->graduation_year }}</div>
            </div>
        </div>
        @endif

        @if ($studentProfile->admission_type)
        <div class="ps-info-row" style="border-bottom:none;">
            <div class="ps-info-icon"><i data-lucide="tag" style="width:14px;height:14px;"></i></div>
            <div>
                <div class="ps-info-label">{{ __('ui.profile.admission_type') }}</div>
                <div class="ps-info-value">{{ ucfirst(str_replace('_', ' ', $studentProfile->admission_type)) }}</div>
            </div>
        </div>
        @endif

    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- CHANGE PASSWORD                                                        --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="card mb-4">
    <div class="card-header ps-card-header">
        <div class="ps-card-icon ps-icon-slate">
            <i data-lucide="lock" style="width:17px;height:17px;"></i>
        </div>
        <div>
            <div class="ps-card-title">{{ __('ui.profile.change_password') }}</div>
            <div class="ps-card-subtitle">{{ __('ui.profile.password_guidance') }}</div>
        </div>
    </div>
    <div class="card-body">

        @if ($errors->has('current_password') || $errors->has('password'))
        <div class="alert dtc-alert-danger d-flex align-items-center gap-2 mb-3" role="alert">
            <i data-lucide="alert-circle" style="width:17px;height:17px;flex-shrink:0;"></i>
            <span>{{ $errors->first('current_password') ?: $errors->first('password') }}</span>
        </div>
        @endif

        <form method="POST"
              action="{{ route($profileRoutePrefix . '.password') }}"
              id="passwordForm">
            @csrf

            <div class="mb-3">
                <label for="current_password" class="ps-label">
                    {{ __('ui.profile.current_password') }} <span class="text-danger">*</span>
                </label>
                <div class="ps-pw-wrap">
                    <input type="password"
                           id="current_password"
                           name="current_password"
                           class="form-control ps-input ps-pw-input @error('current_password') is-invalid @enderror"
                           placeholder="{{ __('ui.profile.enter_current_password') }}"
                           autocomplete="current-password"
                           required>
                    <button type="button"
                            class="ps-pw-toggle"
                            data-target="current_password"
                            aria-label="{{ __('ui.sign_in.toggle_password') }}">
                        <i data-lucide="eye" style="width:16px;height:16px;"></i>
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-sm-6 mb-3">
                    <label for="password" class="ps-label">
                        {{ __('ui.auth.new_password') }} <span class="text-danger">*</span>
                    </label>
                    <div class="ps-pw-wrap">
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control ps-input ps-pw-input @error('password') is-invalid @enderror"
                               placeholder="{{ __('ui.profile.minimum_8') }}"
                               autocomplete="new-password"
                               required
                               minlength="8"
                               >
                        <button type="button"
                                class="ps-pw-toggle"
                                data-target="password"
                                aria-label="{{ __('ui.sign_in.toggle_password') }}">
                            <i data-lucide="eye" style="width:16px;height:16px;"></i>
                        </button>
                    </div>
                </div>

                <div class="col-12 col-sm-6 mb-3">
                    <label for="password_confirmation" class="ps-label">
                        {{ __('ui.profile.confirm_new_password') }} <span class="text-danger">*</span>
                    </label>
                    <div class="ps-pw-wrap">
                        <input type="password"
                               id="password_confirmation"
                               name="password_confirmation"
                               class="form-control ps-input ps-pw-input"
                               placeholder="{{ __('ui.profile.repeat_password') }}"
                               autocomplete="new-password"
                               required
                               minlength="8">
                        <button type="button"
                                class="ps-pw-toggle"
                                data-target="password_confirmation"
                                aria-label="{{ __('ui.sign_in.toggle_password') }}">
                            <i data-lucide="eye" style="width:16px;height:16px;"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i data-lucide="lock" class="mr-1" style="width:15px;height:15px;"></i>
                {{ __('ui.profile.change_password_button') }}
            </button>
        </form>
    </div>
</div>

</div>{{-- /col --}}
</div>{{-- /row --}}

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- AVATAR CROP MODAL                                                      --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="avatarCropModal" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content ps-crop-modal">
            <div class="modal-header ps-crop-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="ps-crop-header-icon">
                        <i data-lucide="image" style="width:16px;height:16px;"></i>
                    </div>
                    <h6 class="modal-title mb-0">{{ __('ui.profile.crop_photo') }}</h6>
                </div>
                <button type="button" class="close ps-crop-close" data-dismiss="modal" aria-label="{{ __('ui.profile.close') }}">
                    <i data-lucide="x" style="width:18px;height:18px;"></i>
                </button>
            </div>
            <div class="modal-body ps-crop-body">
                <div class="ps-crop-hint">
                    <i data-lucide="move" style="width:13px;height:13px;"></i>
                    {{ __('ui.profile.crop_help') }}
                </div>
                <div class="ps-crop-container">
                    <img id="cropImage" src=""                     alt="{{ __('ui.profile.preview') }}" style="max-width:100%;display:block;">
                </div>
            </div>
            <div class="ps-crop-preview-bar">
                <span class="ps-crop-preview-label">{{ __('ui.profile.preview') }}</span>
                <div class="ps-crop-previews">
                    <div class="text-center">
                        <div class="ps-preview-circle ps-preview-lg" id="previewLg"></div>
                        <div class="ps-preview-size">80 px</div>
                    </div>
                    <div class="text-center">
                        <div class="ps-preview-circle ps-preview-md" id="previewMd"></div>
                        <div class="ps-preview-size">48 px</div>
                    </div>
                    <div class="text-center">
                        <div class="ps-preview-circle ps-preview-sm" id="previewSm"></div>
                        <div class="ps-preview-size">32 px</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer ps-crop-footer">
                <button type="button" class="btn ps-crop-btn-secondary" data-dismiss="modal">
                    <i data-lucide="x" style="width:14px;height:14px;"></i> {{ __('ui.profile.cancel') }}
                </button>
                <button type="button" class="btn ps-crop-btn-rotate" id="cropRotateLeft" title="{{ __('ui.profile.rotate_left') }}">
                    <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
                </button>
                <button type="button" class="btn ps-crop-btn-rotate" id="cropRotateRight" title="{{ __('ui.profile.rotate_right') }}">
                    <i data-lucide="rotate-cw" style="width:14px;height:14px;"></i>
                </button>
                <button type="button" class="btn ps-crop-btn-primary" id="cropApplyBtn">
                    <i data-lucide="check" style="width:14px;height:14px;"></i> {{ __('ui.profile.apply_photo') }}
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endsection

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script src="{{ asset('js/profile.js') }}"></script>
@endsection
