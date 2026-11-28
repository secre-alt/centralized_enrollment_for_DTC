<form method="POST" action="{{ route('registrar.applications.resend-email', $application) }}" class="mt-3">
    @csrf
    <button type="submit" class="btn btn-outline-primary">Resend decision email to {{ $application->email }}</button>
</form>
