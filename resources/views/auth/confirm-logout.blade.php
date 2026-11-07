@extends('layouts.public')

@section('title', __('ui.auth.logout_title'))

@section('content')
<div class="container py-5">
    <div class="card mx-auto" style="max-width:440px">
        <div class="card-body p-4">
            <h1 class="h4">{{ __('ui.auth.logout_title') }}</h1>
            <p>{{ __('ui.auth.logout_expired') }}</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <div class="d-flex gap-2">
                    <a href="{{ route('login') }}" class="btn btn-secondary flex-fill">{{ __('ui.auth.stay_signed_in') }}</a>
                    <button type="submit" class="btn btn-primary flex-fill">{{ __('ui.auth.logout') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
