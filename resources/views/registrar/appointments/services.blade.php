@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Appointment Services')

@section('content_header')
    <x-dtc.page-header title="Appointment services" subtitle="Configure which Registrar consultation services can be booked." />
@stop

@section('content')
<div class="container-fluid">
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="card"><div class="card-header"><strong>Add appointment service</strong></div><div class="card-body">
        <form method="POST" action="{{ route('registrar.appointments.services.store') }}" class="row">
            @csrf
            <div class="form-group col-md-6"><label for="new-service-name">Service name</label><input id="new-service-name" class="form-control" name="name" maxlength="160" required></div>
            <div class="form-group col-md-6"><label for="new-service-order">Display order</label><input id="new-service-order" class="form-control" type="number" name="sort_order" min="0" value="10" required></div>
            <div class="form-group col-12"><label for="new-service-description">Description</label><textarea id="new-service-description" class="form-control" name="description" rows="2"></textarea></div>
            <div class="form-group col-12"><label for="new-service-instructions">Instructions and requirements</label><textarea id="new-service-instructions" class="form-control" name="instructions" rows="2"></textarea></div>
            <fieldset class="form-group col-md-6"><legend class="h6">Eligible users</legend>
                @foreach(['student' => 'Students', 'alumni' => 'Alumni', 'new_applicant' => 'Applicants'] as $role => $label)
                    <label class="mr-3"><input type="checkbox" name="eligible_roles[]" value="{{ $role }}"> {{ $label }}</label>
                @endforeach
            </fieldset>
            <div class="form-group col-md-6">
                <input type="hidden" name="requires_approval" value="0">
                <label class="mr-3"><input type="checkbox" name="requires_approval" value="1" checked> Registrar confirmation required</label>
                <input type="hidden" name="is_active" value="0">
                <label class="d-block"><input type="checkbox" name="is_active" value="1"> Active and bookable</label>
            </div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Create service</button></div>
        </form>
    </div></div>

    @foreach($services as $service)
        <div class="card"><div class="card-header d-flex justify-content-between"><strong>{{ $service->name }}</strong><span>{{ $service->appointments_count }} appointment(s)</span></div>
            <div class="card-body"><form method="POST" action="{{ route('registrar.appointments.services.update', $service) }}" class="row">
                @csrf @method('PUT')
                <div class="form-group col-md-6"><label for="service-name-{{ $service->id }}">Service name</label><input id="service-name-{{ $service->id }}" class="form-control" name="name" value="{{ $service->name }}" maxlength="160" required></div>
                <div class="form-group col-md-6"><label for="service-order-{{ $service->id }}">Display order</label><input id="service-order-{{ $service->id }}" class="form-control" type="number" name="sort_order" min="0" value="{{ $service->sort_order }}" required></div>
                <div class="form-group col-12"><label for="service-description-{{ $service->id }}">Description</label><textarea id="service-description-{{ $service->id }}" class="form-control" name="description" rows="2">{{ $service->description }}</textarea></div>
                <div class="form-group col-12"><label for="service-instructions-{{ $service->id }}">Instructions and requirements</label><textarea id="service-instructions-{{ $service->id }}" class="form-control" name="instructions" rows="2">{{ $service->instructions }}</textarea></div>
                <fieldset class="form-group col-md-6"><legend class="h6">Eligible users</legend>
                    @foreach(['student' => 'Students', 'alumni' => 'Alumni', 'new_applicant' => 'Applicants'] as $role => $label)
                        <label class="mr-3"><input type="checkbox" name="eligible_roles[]" value="{{ $role }}" @checked(in_array($role, $service->eligible_roles ?? [], true))> {{ $label }}</label>
                    @endforeach
                </fieldset>
                <div class="form-group col-md-6">
                    <input type="hidden" name="requires_approval" value="0">
                    <label class="mr-3"><input type="checkbox" name="requires_approval" value="1" @checked($service->requires_approval)> Registrar confirmation required</label>
                    <input type="hidden" name="is_active" value="0">
                    <label class="d-block"><input type="checkbox" name="is_active" value="1" @checked($service->is_active)> Active and bookable</label>
                </div>
                <div class="col-12"><button class="btn btn-outline-primary" type="submit">Save service</button><small class="text-muted ml-2">Services stay in history and cannot be deleted.</small></div>
            </form></div>
        </div>
    @endforeach
</div>
@endsection
