<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AppointmentService;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppointmentServiceController extends Controller
{
    private const ROLES = ['student', 'alumni', 'new_applicant'];

    public function index()
    {
        $services = AppointmentService::withCount('appointments')->orderBy('sort_order')->orderBy('name')->get();
        return view('registrar.appointments.services', compact('services'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']);
        $service = AppointmentService::create($data);
        $this->audit($request, 'appointment_service.created', $service);

        return back()->with('success', 'Appointment service created.');
    }

    public function update(Request $request, AppointmentService $service)
    {
        $data = $this->validated($request, $service);
        $service->update($data);
        $this->audit($request, 'appointment_service.updated', $service);

        return back()->with('success', 'Appointment service updated.');
    }

    private function validated(Request $request, ?AppointmentService $service = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', 'unique:appointment_services,name'.($service ? ','.$service->id : '')],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'eligible_roles' => ['required', 'array', 'min:1'],
            'eligible_roles.*' => ['required', 'distinct', 'in:'.implode(',', self::ROLES)],
            'is_active' => ['required', 'boolean'],
            'requires_approval' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);
        $data['is_active'] = (bool) $data['is_active'];
        $data['requires_approval'] = (bool) $data['requires_approval'];

        return $data;
    }

    private function audit(Request $request, string $action, AppointmentService $service): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action,
            'description' => ucfirst(str_replace('_', ' ', $action))." #{$service->id} ({$service->name}).",
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            'metadata' => ['appointment_service_id' => $service->id, 'is_active' => $service->is_active,
                'eligible_roles' => $service->eligible_roles, 'requires_approval' => $service->requires_approval]]);
    }
}
